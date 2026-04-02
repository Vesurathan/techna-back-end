<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staff extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'staffs';

    protected $fillable = [
        'first_name',
        'last_name',
        'nic_number',
        'barcode',
        'date_of_birth',
        'address',
        'gender',
        'blood_group',
        'school_name',
        'qualifications',
        'secondary_phone',
        'secondary_phone_has_whatsapp',
        'medical_notes',
        'image_path',
        'status',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'secondary_phone_has_whatsapp' => 'boolean',
    ];

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'module_staff');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    public function payrollPayments(): HasMany
    {
        return $this->hasMany(StaffPayrollPayment::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}
