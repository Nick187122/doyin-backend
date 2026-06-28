<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class TestimonialController extends Controller
{
    private const PUBLIC_TESTIMONIALS_CACHE_KEY = 'public.testimonials.index';

    /**
     * Public: return visible testimonials, ordered by sort_order.
     */
    public function publicIndex()
    {
        $testimonials = Cache::remember(
            self::PUBLIC_TESTIMONIALS_CACHE_KEY,
            now()->addMinutes(10),
            function () {
                return Testimonial::visible()
                    ->orderBy('sort_order')
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->map(fn ($t) => $this->serializeTestimonial($t));
            }
        );

        return response()->json($testimonials)
            ->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * Admin: return all testimonials.
     */
    public function index()
    {
        $testimonials = Testimonial::orderBy('sort_order')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($t) => $this->serializeTestimonial($t));

        return response()->json($testimonials);
    }

    /**
     * Admin: create a testimonial.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'title'      => 'nullable|string|max:255',
            'company'    => 'nullable|string|max:255',
            'content'    => 'required|string|max:2000',
            'rating'     => 'nullable|integer|min:1|max:5',
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
            'avatar'     => 'nullable|image|max:2048',
            'video'      => 'nullable|file|mimes:mp4,mov,avi,webm|max:20480',
        ]);

        if ($request->hasFile('avatar')) {
            $data['avatar'] = $request->file('avatar')->store('/', 'supabase-testimonials');
        }

        if ($request->hasFile('video')) {
            $data['video_url'] = $request->file('video')->store('/', 'supabase-testimonials');
        }

        $testimonial = Testimonial::create($data);
        $this->clearCache();

        return response()->json($this->serializeTestimonial($testimonial), 201);
    }

    /**
     * Admin: update a testimonial.
     */
    public function update(Request $request, Testimonial $testimonial)
    {
        $data = $request->validate([
            'name'       => 'sometimes|string|max:255',
            'title'      => 'nullable|string|max:255',
            'company'    => 'nullable|string|max:255',
            'content'    => 'sometimes|string|max:2000',
            'rating'     => 'nullable|integer|min:1|max:5',
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
            'avatar'     => 'nullable|image|max:2048',
            'video'      => 'nullable|file|mimes:mp4,mov,avi,webm|max:20480',
        ]);

        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($testimonial->avatar && ! str_starts_with($testimonial->avatar, '/storage/')) {
                Storage::disk('supabase-testimonials')->delete($testimonial->avatar);
            } elseif ($testimonial->avatar) {
                $localPath = str_replace('/storage/', '', $testimonial->avatar);
                Storage::disk('public')->delete($localPath);
            }

            $data['avatar'] = $request->file('avatar')->store('/', 'supabase-testimonials');
        }

        if ($request->hasFile('video')) {
            // Delete old video if exists
            if ($testimonial->video_url && ! str_starts_with($testimonial->video_url, '/storage/')) {
                Storage::disk('supabase-testimonials')->delete($testimonial->video_url);
            }

            $data['video_url'] = $request->file('video')->store('/', 'supabase-testimonials');
        } elseif ($request->boolean('remove_video') && $testimonial->video_url) {
            if (! str_starts_with($testimonial->video_url, '/storage/')) {
                Storage::disk('supabase-testimonials')->delete($testimonial->video_url);
            }

            $data['video_url'] = null;
        }

        $testimonial->update($data);
        $this->clearCache();

        return response()->json($this->serializeTestimonial($testimonial));
    }

    /**
     * Admin: delete a testimonial.
     */
    public function destroy(Testimonial $testimonial)
    {
        // Delete avatar if exists
        if ($testimonial->avatar) {
            if (! str_starts_with($testimonial->avatar, '/storage/')) {
                Storage::disk('supabase-testimonials')->delete($testimonial->avatar);
            } else {
                $localPath = str_replace('/storage/', '', $testimonial->avatar);
                if (Storage::disk('public')->exists($localPath)) {
                    Storage::disk('public')->delete($localPath);
                }
            }
        }

        // Delete video if exists
        if ($testimonial->video_url) {
            if (! str_starts_with($testimonial->video_url, '/storage/')) {
                Storage::disk('supabase-testimonials')->delete($testimonial->video_url);
            } else {
                $localPath = str_replace('/storage/', '', $testimonial->video_url);
                if (Storage::disk('public')->exists($localPath)) {
                    Storage::disk('public')->delete($localPath);
                }
            }
        }

        $testimonial->delete();
        $this->clearCache();

        return response()->json(['message' => 'Testimonial deleted successfully']);
    }

    private function clearCache(): void
    {
        Cache::forget(self::PUBLIC_TESTIMONIALS_CACHE_KEY);
    }

    private function serializeTestimonial(Testimonial $testimonial): Testimonial
    {
        $testimonial->avatar_url = $this->fileUrl($testimonial->avatar);
        $testimonial->video_url = $this->fileUrl($testimonial->video_url);
        return $testimonial;
    }

    private function fileUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, '/storage/')) {
            return asset(ltrim($path, '/'));
        }

        return Storage::disk('supabase-testimonials')->url($path);
    }
}
