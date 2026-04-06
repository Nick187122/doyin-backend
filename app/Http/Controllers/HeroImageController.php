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

        return response()->json($images)
            ->header('Cache-Control', 'public, max-age=300');
    }

    public function adminIndex()
    {
        return response()->json(HeroImage::orderBy('order')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'image' => 'required|image|max:5000',
            'title' => 'nullable|string',
            'is_active' => 'boolean',
            'order' => 'integer',
        ]);

        $path = $request->file('image')->store('hero-images', 'public');
        
        $heroImage = HeroImage::create([
            'image_path' => '/storage/' . $path,
            'title' => $request->title,
            'is_active' => $request->has('is_active') ? $request->is_active : true,
            'order' => $request->order ?? 0,
        ]);

        $this->clearHeroImageCache();

        return response()->json($heroImage, 201);
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

        return response()->json($heroImage);
    }

    public function destroy(HeroImage $heroImage)
    {
        $path = str_replace('/storage/', '', $heroImage->image_path);
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
        $heroImage->delete();
        $this->clearHeroImageCache();
        return response()->json(['message' => 'Image deleted']);
    }

    private function clearHeroImageCache(): void
    {
        Cache::forget(self::PUBLIC_HERO_IMAGES_CACHE_KEY);
    }
}
