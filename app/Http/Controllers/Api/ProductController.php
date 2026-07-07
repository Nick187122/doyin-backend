<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    private const PUBLIC_PRODUCTS_CACHE_KEY = 'public.products.index';
    private const PUBLIC_PRODUCT_CACHE_PREFIX = 'public.products.show.';
    private const PUBLIC_SITEMAP_PRODUCTS_CACHE_KEY = 'public.products.sitemap';

    public function index()
    {
        return response()->json($this->serializeProducts(
            Product::with('category')->orderBy('name')->get()
        ));
    }

    public function store(Request $request)
    {
        $category = Category::findOrFail($request->input('category_id'));

        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
            'max_flow_rate' => 'nullable|string|max:100',
            'max_height' => 'nullable|string|max:100',
            'recommended_depth' => 'nullable|string|max:100',
            'ideal_power' => 'nullable|string|max:100',
            'performance_curves' => 'nullable|json',
            'in_stock' => 'boolean',
        ]);

        $data = $this->normalizeCategorySpecificFields($data, $category);

        if ($similarProduct = $this->findSimilarProduct($data)) {
            return response()->json([
                'message' => 'A similar product already exists. Edit the existing product instead of creating a duplicate.',
                'similar_product' => $this->serializeProduct($similarProduct->load('category')),
            ], 422);
        }

        if (isset($data['performance_curves']) && is_string($data['performance_curves'])) {
            $data['performance_curves'] = json_decode($data['performance_curves'], true);
        }

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('/', 'supabase-products');
        }

        unset($data['image']);

        $product = Product::create($data);
        $product->load('category');

        $this->clearPublicProductCaches($product->id);

        return response()->json($this->serializeProduct($product), 201);
    }

    public function update(Request $request, Product $product)
    {
        $category = Category::findOrFail($request->input('category_id'));

        $data = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
            'max_flow_rate' => 'nullable|string|max:100',
            'max_height' => 'nullable|string|max:100',
            'recommended_depth' => 'nullable|string|max:100',
            'ideal_power' => 'nullable|string|max:100',
            'performance_curves' => 'nullable|json',
            'in_stock' => 'boolean',
        ]);

        $data = $this->normalizeCategorySpecificFields($data, $category);

        if ($similarProduct = $this->findSimilarProduct($data, $product->id)) {
            return response()->json([
                'message' => 'A similar product already exists. Edit that product instead of saving a duplicate.',
                'similar_product' => $this->serializeProduct($similarProduct->load('category')),
            ], 422);
        }

        if (isset($data['performance_curves']) && is_string($data['performance_curves'])) {
            $data['performance_curves'] = json_decode($data['performance_curves'], true);
        }

        if ($request->hasFile('image')) {
            if ($product->image_path && ! str_starts_with($product->image_path, '/storage/')) {
                Storage::disk('supabase-products')->delete($product->image_path);
            } elseif ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }

            $data['image_path'] = $request->file('image')->store('/', 'supabase-products');
        }

        unset($data['image']);

        $product->update($data);
        $product->load('category');

        $this->clearPublicProductCaches($product->id);

        return response()->json($this->serializeProduct($product));
    }

    public function destroy(Product $product)
    {
        if ($product->image_path) {
            if (! str_starts_with($product->image_path, '/storage/')) {
                Storage::disk('supabase-products')->delete($product->image_path);
            } else {
                Storage::disk('public')->delete($product->image_path);
            }
        }

        $productId = $product->id;
        $product->delete();

        $this->clearPublicProductCaches($productId);

        return response()->json(['message' => 'Product deleted.']);
    }

    public function publicIndex()
    {
        $products = Cache::remember(
            self::PUBLIC_PRODUCTS_CACHE_KEY,
            now()->addMinutes(10),
            fn () => $this->serializeProducts(Product::with('category')->orderBy('name')->get())
        );

        return response()->json($products)
            ->header('Cache-Control', 'public, max-age=300');
    }

    public function publicSitemapIndex()
    {
        $products = Cache::remember(
            self::PUBLIC_SITEMAP_PRODUCTS_CACHE_KEY,
            now()->addMinutes(30),
            fn () => Product::query()
                ->select(['id', 'updated_at'])
                ->orderBy('id')
                ->get()
                ->map(fn (Product $product) => [
                    'id' => $product->id,
                    'updated_at' => $product->updated_at?->toISOString(),
                ])
                ->all()
        );

        return response()->json($products)
            ->header('Cache-Control', 'public, max-age=1800');
    }

    public function incrementView(Product $product)
    {
        $product->increment('views_count');

        return response()->json(['message' => 'View recorded']);
    }

    public function publicShow($id)
    {
        $product = Cache::remember(
            self::PUBLIC_PRODUCT_CACHE_PREFIX . $id,
            now()->addMinutes(10),
            function () use ($id) {
                return $this->serializeProduct(
                    Product::with('category')->findOrFail($id)
                );
            }
        );

        return response()->json($product)
            ->header('Cache-Control', 'public, max-age=300');
    }

    private function clearPublicProductCaches(int $productId): void
    {
        Cache::forget(self::PUBLIC_PRODUCTS_CACHE_KEY);
        Cache::forget(self::PUBLIC_PRODUCT_CACHE_PREFIX . $productId);
        Cache::forget(self::PUBLIC_SITEMAP_PRODUCTS_CACHE_KEY);
        Cache::forget('public.categories.index');
    }

    private function serializeProducts($products)
    {
        return $products->map(fn (Product $product) => $this->serializeProduct($product));
    }

    private function normalizeCategorySpecificFields(array $data, Category $category): array
    {
        if ($category->is_pump) {
            if (! $category->has_ideal_power) {
                $data['ideal_power'] = null;
            }

            return $data;
        }

        $data['max_flow_rate'] = null;
        $data['max_height'] = null;
        $data['recommended_depth'] = null;
        $data['ideal_power'] = null;

        return $data;
    }

    private function findSimilarProduct(array $data, ?int $ignoreProductId = null): ?Product
    {
        $incomingName = $this->normalizeComparableText($data['name'] ?? '');
        $incomingDescription = $this->normalizeComparableText($data['description'] ?? '');

        if ($incomingName === '') {
            return null;
        }

        $products = Product::query()
            ->when($ignoreProductId, fn ($query) => $query->where('id', '!=', $ignoreProductId))
            ->orderBy('name')
            ->get();

        foreach ($products as $product) {
            $existingName = $this->normalizeComparableText($product->name);
            $existingDescription = $this->normalizeComparableText($product->description ?? '');

            if ($existingName === $incomingName
                && ($incomingDescription === '' || $existingDescription === '' || $incomingDescription === $existingDescription)) {
                return $product;
            }
        }

        return null;
    }

    private function normalizeComparableText(?string $value): string
    {
        $normalized = Str::of((string) $value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->value();

        return $normalized;
    }

    private function serializeProduct(Product $product): Product
    {
        $product->image_url = $this->imageUrl($product->image_path);

        return $product;
    }

    private function imageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        // Legacy local storage path — keep existing format
        if (str_starts_with($path, '/storage/')) {
            return asset(ltrim($path, '/'));
        }

        // New Supabase S3 path
        return Storage::disk('supabase-products')->url($path);
    }
}
