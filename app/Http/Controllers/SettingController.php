<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class SettingController extends Controller
{
    private const PUBLIC_SETTINGS_CACHE_KEY = 'public.settings.index';

    private const ALLOWED_SETTING_KEYS = [
        'store_name',
        'contact_email',
        'contact_phone',
        'contact_address',
        'facebook_url',
        'instagram_url',
        'about_video_url',
    ];

    public function index()
    {
        $settings = Cache::remember(
            self::PUBLIC_SETTINGS_CACHE_KEY,
            now()->addMinutes(10),
            fn () => Setting::all()->pluck('value', 'key')
        );

        return response()->json($settings)
            ->header('Cache-Control', 'public, max-age=300');
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'settings' => 'nullable|array',
            'about_image' => 'nullable|image|max:5120',
        ]);

        $validator->after(function ($validator) use ($request) {
            $settings = $request->input('settings', []);

            if (! is_array($settings)) {
                return;
            }

            foreach ($settings as $key => $value) {
                if (! in_array($key, self::ALLOWED_SETTING_KEYS, true)) {
                    $validator->errors()->add("settings.$key", 'This setting cannot be updated.');
                    continue;
                }

                if ($value !== null && ! is_string($value)) {
                    $validator->errors()->add("settings.$key", 'The setting value must be a string.');
                }
            }

            $this->validateSettingUrls($validator, $settings);
        });

        $data = $validator->validate();

        if (isset($data['settings']) && is_array($data['settings'])) {
            foreach ($data['settings'] as $key => $value) {
                Setting::updateOrCreate(
                    ['key' => $key],
                    ['value' => $value]
                );
            }
        }

        if ($request->hasFile('about_image')) {
            $path = $request->file('about_image')->store('settings', 'public');

            Setting::updateOrCreate(
                ['key' => 'about_image'],
                ['value' => '/storage/' . $path]
            );
        }

        Cache::forget(self::PUBLIC_SETTINGS_CACHE_KEY);

        return response()->json(['message' => 'Settings updated successfully']);
    }

    private function validateSettingUrls($validator, array $settings): void
    {
        $basicUrlFields = ['facebook_url', 'instagram_url'];

        foreach ($basicUrlFields as $field) {
            if (! empty($settings[$field]) && ! filter_var($settings[$field], FILTER_VALIDATE_URL)) {
                $validator->errors()->add("settings.$field", 'The value must be a valid URL.');
            }
        }

        if (empty($settings['about_video_url'])) {
            return;
        }

        $url = $settings['about_video_url'];

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            $validator->errors()->add('settings.about_video_url', 'The video URL must be a valid URL.');
            return;
        }

        $host = strtolower(parse_url($url, PHP_URL_HOST) ?? '');
        $allowedHosts = [
            'youtube.com',
            'www.youtube.com',
            'youtu.be',
            'www.youtu.be',
            'vimeo.com',
            'www.vimeo.com',
            'player.vimeo.com',
        ];

        if (! in_array($host, $allowedHosts, true)) {
            $validator->errors()->add(
                'settings.about_video_url',
                'The video URL must be a YouTube or Vimeo link.'
            );
        }
    }
}
