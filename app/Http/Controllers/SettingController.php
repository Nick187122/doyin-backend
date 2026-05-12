<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
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
        'homepage_new_arrivals_enabled',
        'homepage_new_arrivals_badge',
        'homepage_new_arrivals_title',
        'homepage_new_arrivals_copy',
        'homepage_new_arrivals_count',
        'homepage_new_arrivals_category_id',
        'homepage_featured_products_enabled',
        'homepage_featured_products_badge',
        'homepage_featured_products_title',
        'homepage_featured_products_copy',
        'homepage_featured_product_ids',
    ];

    public function index()
    {
        $settings = Cache::remember(
            self::PUBLIC_SETTINGS_CACHE_KEY,
            now()->addMinutes(1),
            fn () => Setting::all()->pluck('value', 'key')
        );

        return response()->json($settings)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
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
            $this->validateHomepageSettings($validator, $settings);
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

        $this->validateHomepageSettings($validator, $settings);
    }

    private function validateHomepageSettings($validator, array $settings): void
    {
        if (isset($settings['homepage_new_arrivals_enabled'])
            && ! in_array((string) $settings['homepage_new_arrivals_enabled'], ['0', '1'], true)) {
            $validator->errors()->add(
                'settings.homepage_new_arrivals_enabled',
                'The new arrivals toggle must be 1 or 0.'
            );
        }

        if (isset($settings['homepage_new_arrivals_count'])
            && $settings['homepage_new_arrivals_count'] !== ''
            && (! ctype_digit((string) $settings['homepage_new_arrivals_count'])
                || (int) $settings['homepage_new_arrivals_count'] < 1
                || (int) $settings['homepage_new_arrivals_count'] > 12)) {
            $validator->errors()->add(
                'settings.homepage_new_arrivals_count',
                'The new arrivals count must be a whole number between 1 and 12.'
            );
        }

        if (isset($settings['homepage_new_arrivals_category_id'])
            && $settings['homepage_new_arrivals_category_id'] !== ''
            ) {
            $categoryId = (string) $settings['homepage_new_arrivals_category_id'];

            if (! ctype_digit($categoryId) || ! Category::query()->whereKey((int) $categoryId)->exists()) {
                $validator->errors()->add(
                    'settings.homepage_new_arrivals_category_id',
                    'The selected category must be a valid category id.'
                );
            }
        }

        if (isset($settings['homepage_featured_products_enabled'])
            && ! in_array((string) $settings['homepage_featured_products_enabled'], ['0', '1'], true)) {
            $validator->errors()->add(
                'settings.homepage_featured_products_enabled',
                'The featured products toggle must be 1 or 0.'
            );
        }

        if (isset($settings['homepage_featured_product_ids']) && $settings['homepage_featured_product_ids'] !== '') {
            $rawIds = collect(explode(',', (string) $settings['homepage_featured_product_ids']))
                ->map(fn ($value) => trim($value))
                ->filter();

            if ($rawIds->contains(fn ($value) => ! ctype_digit($value))) {
                $validator->errors()->add(
                    'settings.homepage_featured_product_ids',
                    'Featured products must contain only valid product ids.'
                );

                return;
            }

            $ids = $rawIds->map(fn ($value) => (int) $value)->unique()->values();

            if ($ids->count() > 8) {
                $validator->errors()->add(
                    'settings.homepage_featured_product_ids',
                    'Select at most 8 featured products.'
                );
            }

            $existingIds = Product::query()->whereIn('id', $ids)->pluck('id')->all();
            $missingIds = $ids->diff($existingIds);

            if ($missingIds->isNotEmpty()) {
                $validator->errors()->add(
                    'settings.homepage_featured_product_ids',
                    'One or more featured products no longer exist.'
                );
            }
        }
    }
}
