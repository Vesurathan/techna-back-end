<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubModule extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'name',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
