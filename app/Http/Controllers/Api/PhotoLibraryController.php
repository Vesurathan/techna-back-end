<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PhotoFolder;
use App\Models\PhotoLibraryImage;
use App\Support\MediaDisk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PhotoLibraryController extends Controller
{
    public function indexFolders(Request $request)
    {
        $parentId = $request->query('parent_id');

        $query = PhotoFolder::query()->orderBy('name');

        if ($parentId === null || $parentId === '') {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', (int) $parentId);
        }

        $folders = $query->withCount(['images', 'children'])->get();

        return response()->json([
            'folders' => $folders->map(fn (PhotoFolder $f) => $this->formatFolder($f)),
        ]);
    }

    public function storeFolder(Request $request)
    {
        $parentId = $request->input('parent_id');

        $validated = $request->validate(
            [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('photo_folders', 'name')->where(function ($query) use ($parentId) {
                        if ($parentId === null || $parentId === '') {
                            $query->whereNull('parent_id');
                        } else {
                            $query->where('parent_id', (int) $parentId);
                        }
                    }),
                ],
                'parent_id' => 'nullable|integer|exists:photo_folders,id',
            ],
            [
                'name.unique' => 'A folder with this name already exists in this location.',
            ]
        );

        $folder = PhotoFolder::create([
            'name' => $validated['name'],
            'parent_id' => $validated['parent_id'] ?? null,
        ]);

        $folder->loadCount(['images', 'children']);

        return response()->json([
            'folder' => $this->formatFolder($folder),
            'message' => 'Folder created',
        ], 201);
    }

    public function updateFolder(Request $request, PhotoFolder $folder)
    {
        $validated = $request->validate(
            [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('photo_folders', 'name')
                        ->ignore($folder->id)
                        ->where(function ($query) use ($folder) {
                            if ($folder->parent_id === null) {
                                $query->whereNull('parent_id');
                            } else {
                                $query->where('parent_id', $folder->parent_id);
                            }
                        }),
                ],
            ],
            [
                'name.unique' => 'A folder with this name already exists in this location.',
            ]
        );

        $folder->update(['name' => $validated['name']]);
        $folder->loadCount(['images', 'children']);

        return response()->json([
            'folder' => $this->formatFolder($folder),
            'message' => 'Folder updated',
        ]);
    }

    public function destroyFolder(PhotoFolder $folder)
    {
        DB::transaction(function () use ($folder) {
            $this->deleteFolderTree($folder);
        });

        return response()->json(['message' => 'Folder deleted']);
    }

    public function indexImages(PhotoFolder $folder)
    {
        $images = $folder->images()->orderByDesc('created_at')->get();

        return response()->json([
            'files' => $images->map(fn (PhotoLibraryImage $img) => $this->formatFile($img)),
        ]);
    }

    public function storeImage(Request $request, PhotoFolder $folder)
    {
        $request->validate([
            'file' => 'required|file|max:51200',
        ]);

        $file = $request->file('file');
        $path = MediaDisk::storeUpload($file, 'photo-library');

        $image = PhotoLibraryImage::create([
            'photo_folder_id' => $folder->id,
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'is_active' => true,
        ]);

        return response()->json([
            'file' => $this->formatFile($image),
            'message' => 'File uploaded',
        ], 201);
    }

    public function updateImage(Request $request, PhotoLibraryImage $photoLibraryImage)
    {
        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $photoLibraryImage->update(['is_active' => $validated['is_active']]);

        return response()->json([
            'file' => $this->formatFile($photoLibraryImage->fresh()),
            'message' => 'File updated',
        ]);
    }

    public function destroyImage(PhotoLibraryImage $photoLibraryImage)
    {
        $this->deleteImageFile($photoLibraryImage);
        $photoLibraryImage->delete();

        return response()->json(['message' => 'File deleted']);
    }

    private function deleteFolderTree(PhotoFolder $folder): void
    {
        $children = $folder->children()->get();
        foreach ($children as $child) {
            $this->deleteFolderTree($child);
        }

        foreach ($folder->images()->get() as $img) {
            $this->deleteImageFile($img);
            $img->delete();
        }

        $folder->delete();
    }

    private function deleteImageFile(PhotoLibraryImage $image): void
    {
        MediaDisk::deleteIfExists($image->file_path);
    }

    private function formatFolder(PhotoFolder $folder): array
    {
        return [
            'id' => (string) $folder->id,
            'name' => $folder->name,
            'parent_id' => $folder->parent_id !== null ? (string) $folder->parent_id : null,
            'files_count' => (int) ($folder->images_count ?? $folder->images()->count()),
            'children_count' => (int) ($folder->children_count ?? $folder->children()->count()),
        ];
    }

    private function formatFile(PhotoLibraryImage $image): array
    {
        return [
            'id' => (string) $image->id,
            'photo_folder_id' => (string) $image->photo_folder_id,
            'url' => MediaDisk::publicUrl($image->file_path) ?? '',
            'original_name' => $image->original_name,
            'mime_type' => $image->mime_type,
            'is_active' => (bool) $image->is_active,
            'created_at' => $image->created_at?->toIso8601String(),
        ];
    }

}
