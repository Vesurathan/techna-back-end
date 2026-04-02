<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Module extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'sub_modules_count',
        'amount',
    ];

    public function staffs(): BelongsToMany
    {
        return $this->belongsToMany(Staff::class, 'module_staff');
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'module_student');
    }

    public function fees()
    {
        return $this->hasMany(ModuleFee::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
