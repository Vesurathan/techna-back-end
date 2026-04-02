<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GalleryCategory;
use App\Models\GalleryImage;
use App\Models\Module;
use App\Models\Staff;
use Illuminate\Http\Request;

/**
 * Unauthenticated read-only data for the public marketing site.
 */
class PublicWebsiteController extends Controller
{
    public function modules(Request $request)
    {
        $onlyActiveStaff = ! $request->boolean('with_inactive_staff');

        $modules = Module::query()
            ->with(['staffs' => function ($q) use ($onlyActiveStaff) {
                if ($onlyActiveStaff) {
                    $q->where('status', 'active');
                }
                $q->orderBy('first_name')->orderBy('last_name');
            }])
            ->orderBy('name')
            ->get();

        return response()->json([
            'modules' => $modules->map(fn (Module $m) => $this->formatPublicModule($m)),
        ]);
    }

    public function staff(Request $request)
    {
        $query = Staff::query()
            ->where('status', 'active')
            ->with(['modules' => function ($q) {
                $q->orderBy('name');
            }])
            ->orderBy('first_name')
            ->orderBy('last_name');

        if ($request->filled('module_id')) {
            $query->whereHas('modules', fn ($q) => $q->where('modules.id', $request->module_id));
        }

        $staff = $query->get();

        return response()->json([
            'staff' => $staff->map(fn (Staff $s) => $this->formatPublicStaff($s)),
        ]);
    }

    public function staffShow(Staff $staff)
    {
        if ($staff->status !== 'active') {
            return response()->json(['message' => 'Not found'], 404);
        }

        $staff->load(['modules' => function ($q) {
            $q->orderBy('name');
        }]);

        return response()->json([
            'staff' => $this->formatPublicStaff($staff),
        ]);
    }

    public function galleryCategories()
    {
        $categories = GalleryCategory::query()
            ->withCount('images')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'categories' => $categories->map(fn (GalleryCategory $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'sort_order' => (int) $c->sort_order,
                'images_count' => (int) $c->images_count,
            ]),
        ]);
    }

    public function galleryCategoryImages(GalleryCategory $galleryCategory)
    {
        $images = $galleryCategory->images()->orderByDesc('created_at')->get();

        return response()->json([
            'images' => $images->map(fn (GalleryImage $img) => [
                'id' => $img->id,
                'url' => $this->resolvePublicUrl($img->file_path),
                'original_name' => $img->original_name,
            ]),
        ]);
    }

    private function formatPublicModule(Module $module): array
    {
        return [
            'id' => $module->id,
            'name' => $module->name,
            'category' => $module->category,
            'subModulesCount' => $module->sub_modules_count,
            'amount' => (float) $module->amount,
            'staff' => $module->staffs->map(fn (Staff $s) => [
                'id' => $s->id,
                'fullName' => $s->full_name,
                'imageUrl' => $this->resolvePublicUrl($s->image_path),
                'qualifications' => $s->qualifications,
            ]),
        ];
    }

    private function formatPublicStaff(Staff $staff): array
    {
        return [
            'id' => $staff->id,
            'firstName' => $staff->first_name,
            'lastName' => $staff->last_name,
            'fullName' => $staff->full_name,
            'schoolName' => $staff->school_name,
            'qualifications' => $staff->qualifications,
            'imageUrl' => $this->resolvePublicUrl($staff->image_path),
            'modules' => $staff->modules->map(fn (Module $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'category' => $m->category,
            ]),
        ];
    }

    private function resolvePublicUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $path = trim($path);

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $base = rtrim(config('app.url'), '/');

        if (str_starts_with($path, '/storage/')) {
            return $base.$path;
        }

        return $base.'/storage/'.ltrim($path, '/');
    }
}
