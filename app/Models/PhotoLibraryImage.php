<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotoLibraryImage extends Model
{
    protected $fillable = [
        'photo_folder_id',
        'file_path',
        'original_name',
        'mime_type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(PhotoFolder::class, 'photo_folder_id');
    }
}
