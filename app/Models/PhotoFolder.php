<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhotoFolder extends Model
{
    protected $fillable = [
        'parent_id',
        'name',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(PhotoFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(PhotoFolder::class, 'parent_id')->orderBy('name');
    }

    public function images(): HasMany
    {
        return $this->hasMany(PhotoLibraryImage::class, 'photo_folder_id')->orderByDesc('created_at');
    }
}
