<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Timetable extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch',
        'date',
        'weekday',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function slots(): HasMany
    {
        return $this->hasMany(TimetableSlot::class);
    }

    public function getWeekdayFromDate(): string
    {
        return $this->date->format('l'); // Returns full weekday name (Monday, Tuesday, etc.)
    }
}
