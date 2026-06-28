<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\HeroImage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class HeroImageController extends Controller
{
    private const PUBLIC_HERO_IMAGES_CACHE_KEY = 'public.hero-images.index';

    public function index()
    {
        $images = Cache::remember(
            self::PUBLIC_HERO_IMAGES_CACHE_KEY,
            now()->addMinutes(10),
            fn () => HeroImage::where('is_active', true)->orderBy('order')->get()
        );

        $images = $images->map(fn ($image) => $this->serializeHeroImage($image));

        return response()->json($images)
            ->header('Cache-Control', 'public, max-age=300');
    }

    public function adminIndex()
    {
        $images = HeroImage::orderBy('order')->get()->map(fn ($image) => $this->serializeHeroImage($image));

        return response()->json($images);
    }

    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:5000',
            'title' => 'nullable|string',
            'is_active' => 'boolean',
            'order' => 'integer',
        ]);

        $path = $request->file('image')->store('/', 'supabase-hero-images');

        $heroImage = HeroImage::create([
            'image_path' => $path,
            'title' => $request->title,
            'is_active' => $request->has('is_active') ? $request->is_active : true,
            'order' => $request->order ?? 0,
        ]);

        $this->clearHeroImageCache();

        return response()->json($this->serializeHeroImage($heroImage), 201);
    }

    public function update(Request $request, HeroImage $heroImage)
    {
        $request->validate([
            'title' => 'nullable|string',
            'is_active' => 'boolean',
            'order' => 'integer',
        ]);

        $heroImage->update($request->only(['title', 'is_active', 'order']));
        $this->clearHeroImageCache();

        return response()->json($this->serializeHeroImage($heroImage));
    }

    public function destroy(HeroImage $heroImage)
    {
        $path = $heroImage->image_path;

        if ($path) {
            if (str_starts_with($path, '/storage/')) {
                $localPath = str_replace('/storage/', '', $path);
                if (Storage::disk('public')->exists($localPath)) {
                    Storage::disk('public')->delete($localPath);
                }
            } else {
                Storage::disk('supabase-hero-images')->delete($path);
            }
        }

        $heroImage->delete();
        $this->clearHeroImageCache();

        return response()->json(['message' => 'Image deleted']);
    }

    private function clearHeroImageCache(): void
    {
        Cache::forget(self::PUBLIC_HERO_IMAGES_CACHE_KEY);
    }

    private function serializeHeroImage(HeroImage $image): HeroImage
    {
        $image->image_url = $this->imageUrl($image->image_path);
        return $image;
    }

    private function imageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, '/storage/')) {
            return asset(ltrim($path, '/'));
        }

        return Storage::disk('supabase-hero-images')->url($path);
    }
}
