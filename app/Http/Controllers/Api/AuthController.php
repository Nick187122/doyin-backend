<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AdminPasswordChangeOtpNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_token' => 'required|string|max:255',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user->tokens()->delete();
        $user->forceFill([
            'active_device_token' => $request->device_token,
        ])->save();

        $token = $user->createToken('admin-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'must_change_password' => $user->must_change_password,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function requestPasswordChangeOtp(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        if (Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['New password must be different from the current password.'],
            ]);
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->forceFill([
            'password_change_otp' => Hash::make($otp),
            'password_change_otp_expires_at' => now()->addMinutes(10),
        ])->save();

        Notification::route('mail', config('mail.admin_otp_to', $user->email))
            ->notify(new AdminPasswordChangeOtpNotification($otp));

        return response()->json([
            'message' => 'A verification code has been sent to your admin email address.',
            'expires_in' => 600,
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed',
            'otp' => 'required|digits:6',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        if (Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['New password must be different from the current password.'],
            ]);
        }

        if (! $user->password_change_otp || ! $user->password_change_otp_expires_at) {
            throw ValidationException::withMessages([
                'otp' => ['Request a verification code before changing your password.'],
            ]);
        }

        if ($user->password_change_otp_expires_at->isPast()) {
            $user->forceFill([
                'password_change_otp' => null,
                'password_change_otp_expires_at' => null,
            ])->save();

            throw ValidationException::withMessages([
                'otp' => ['The verification code has expired. Request a new code and try again.'],
            ]);
        }

        if (! Hash::check($request->otp, $user->password_change_otp)) {
            throw ValidationException::withMessages([
                'otp' => ['The verification code is invalid.'],
            ]);
        }

        $user->password = Hash::make($request->password);
        $user->must_change_password = false;
        $user->password_change_otp = null;
        $user->password_change_otp_expires_at = null;
        $user->save();

        $user->tokens()->delete();
        $token = $user->createToken('admin-token')->plainTextToken;

        return response()->json([
            'message' => 'Password changed successfully.',
            'token' => $token,
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
            'must_change_password' => $request->user()->must_change_password,
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        $user->currentAccessToken()?->delete();

        if ($user->tokens()->count() === 0) {
            $user->forceFill(['active_device_token' => null])->save();
        }

        return response()->json(['message' => 'Logged out successfully.']);
    }
}
