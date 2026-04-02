<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'timetable_id',
        'start_time',
        'end_time',
        'module_id',
        'staff_id',
        'classroom_id',
        'classroom',
        'interval_time',
    ];

    // No casts needed for time fields - they're stored as time strings

    public function timetable(): BelongsTo
    {
        return $this->belongsTo(Timetable::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function classroomRelation(): BelongsTo
    {
        return $this->belongsTo(Classroom::class, 'classroom_id');
    }
}
