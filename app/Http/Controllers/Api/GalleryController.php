<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GalleryCategory;
use App\Models\GalleryImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class GalleryController extends Controller
{
    public function indexCategories()
    {
        $categories = GalleryCategory::query()
            ->withCount('images')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'categories' => $categories->map(fn (GalleryCategory $c) => $this->formatCategory($c)),
        ]);
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('gallery_categories', 'name')],
            'sort_order' => 'nullable|integer|min:0|max:999999',
        ]);

        $category = GalleryCategory::create([
            'name' => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);
        $category->loadCount('images');

        return response()->json([
            'category' => $this->formatCategory($category),
            'message' => 'Category created',
        ], 201);
    }

    public function updateCategory(Request $request, GalleryCategory $galleryCategory)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('gallery_categories', 'name')->ignore($galleryCategory->id),
            ],
            'sort_order' => 'nullable|integer|min:0|max:999999',
        ]);

        $galleryCategory->update([
            'name' => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? $galleryCategory->sort_order,
        ]);
        $galleryCategory->loadCount('images');

        return response()->json([
            'category' => $this->formatCategory($galleryCategory),
            'message' => 'Category updated',
        ]);
    }

    public function destroyCategory(GalleryCategory $galleryCategory)
    {
        DB::transaction(function () use ($galleryCategory) {
            foreach ($galleryCategory->images()->get() as $img) {
                $this->deleteImageFile($img);
                $img->delete();
            }
            $galleryCategory->delete();
        });

        return response()->json(['message' => 'Category deleted']);
    }

    public function indexImages(GalleryCategory $galleryCategory)
    {
        $images = $galleryCategory->images()->orderByDesc('created_at')->get();

        return response()->json([
            'images' => $images->map(fn (GalleryImage $img) => $this->formatImage($img)),
        ]);
    }

    public function storeBulkImages(Request $request, GalleryCategory $galleryCategory)
    {
        $validated = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:5'],
            'images.*' => ['required', 'file', 'image', 'max:51200'],
        ], [
            'images.max' => 'You can upload at most 5 images at once.',
        ]);

        $created = [];

        foreach ($validated['images'] as $file) {
            $path = $file->store('gallery/'.$galleryCategory->id, 'public');
            $created[] = GalleryImage::create([
                'gallery_category_id' => $galleryCategory->id,
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'sort_order' => 0,
            ]);
        }

        return response()->json([
            'images' => array_map(fn (GalleryImage $img) => $this->formatImage($img), $created),
            'message' => count($created).' image(s) uploaded',
        ], 201);
    }

    public function destroyImage(GalleryImage $galleryImage)
    {
        $this->deleteImageFile($galleryImage);
        $galleryImage->delete();

        return response()->json(['message' => 'Image deleted']);
    }

    private function deleteImageFile(GalleryImage $image): void
    {
        if ($image->file_path && Storage::disk('public')->exists($image->file_path)) {
            Storage::disk('public')->delete($image->file_path);
        }
    }

    private function formatCategory(GalleryCategory $category): array
    {
        return [
            'id' => (string) $category->id,
            'name' => $category->name,
            'sort_order' => (int) $category->sort_order,
            'images_count' => (int) ($category->images_count ?? $category->images()->count()),
        ];
    }

    private function formatImage(GalleryImage $image): array
    {
        return [
            'id' => (string) $image->id,
            'gallery_category_id' => (string) $image->gallery_category_id,
            'url' => $this->publicUrl($image->file_path),
            'original_name' => $image->original_name,
            'mime_type' => $image->mime_type,
            'created_at' => $image->created_at?->toIso8601String(),
        ];
    }

    private function publicUrl(string $path): string
    {
        $relative = Storage::disk('public')->url($path);
        if (str_starts_with($relative, 'http://') || str_starts_with($relative, 'https://')) {
            return $relative;
        }

        return rtrim(config('app.url'), '/').'/'.ltrim($relative, '/');
    }
}
