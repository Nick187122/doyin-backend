<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    private const PUBLIC_CATEGORIES_CACHE_KEY = 'public.categories.index';

    public function index()
    {
        $categories = Cache::remember(
            self::PUBLIC_CATEGORIES_CACHE_KEY,
            now()->addMinutes(10),
            fn () => Category::withCount('products')->orderBy('name')->get()
        );

        return response()->json($categories)
            ->header('Cache-Control', 'public, max-age=300');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255|unique:categories,name',
            'is_pump' => 'boolean',
            'has_ideal_power' => 'boolean',
        ]);

        $data['is_pump'] = (bool) ($data['is_pump'] ?? true);
        $data['has_ideal_power'] = $data['is_pump'] ? (bool) ($data['has_ideal_power'] ?? false) : false;

        $category = Category::create($data);
        $this->clearCategoryCache();

        return response()->json($category, 201);
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:255|unique:categories,name,' . $category->id,
            'is_pump' => 'boolean',
            'has_ideal_power' => 'boolean',
        ]);

        $data['is_pump'] = (bool) ($data['is_pump'] ?? $category->is_pump);
        $data['has_ideal_power'] = $data['is_pump'] ? (bool) ($data['has_ideal_power'] ?? false) : false;

        $category->update($data);
        $this->clearCategoryCache();

        return response()->json($category);
    }

    public function destroy(Category $category)
    {
        $category->delete();
        $this->clearCategoryCache();
        return response()->json(['message' => 'Category deleted.']);
    }

    private function clearCategoryCache(): void
    {
        Cache::forget(self::PUBLIC_CATEGORIES_CACHE_KEY);
        Cache::forget('public.products.index');
    }
}
