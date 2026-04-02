<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'admission_number',
        'barcode',
        'first_name',
        'last_name',
        'date_of_birth',
        'gender',
        'nic_number',
        'personal_phone',
        'parent_phone',
        'personal_phone_has_whatsapp',
        'parent_phone_has_whatsapp',
        'admission_batch',
        'address',
        'school_name',
        'blood_group',
        'medical_notes',
        'image_path',
        'admission_fee',
        'module_total_amount',
        'paid_amount',
        'payment_type',
        'status',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'personal_phone_has_whatsapp' => 'boolean',
        'parent_phone_has_whatsapp' => 'boolean',
        'admission_fee' => 'decimal:2',
        'module_total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
    ];

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'module_student');
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }
}