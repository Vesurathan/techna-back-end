<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleFee extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'monthly_fee',
        'effective_from',
        'effective_to',
        'description',
    ];

    protected $casts = [
        'monthly_fee' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
