<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $deviceToken = $request->header('X-Device-Token');

        if (! $deviceToken || $user->active_device_token !== $deviceToken) {
            $request->user()->currentAccessToken()?->delete();

            return response()->json([
                'message' => 'Session invalidated. Another device has logged in.',
            ], 401);
        }

        return $next($request);
    }
}
