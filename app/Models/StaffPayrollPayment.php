<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffPayrollPayment extends Model
{
    protected $fillable = [
        'staff_id',
        'pay_year',
        'pay_month',
        'pay_type',
        'gross_amount',
        'deductions',
        'net_amount',
        'payment_date',
        'payment_method',
        'account_holder_name',
        'bank_name',
        'account_number',
        'bank_branch',
        'iban_swift',
        'transfer_reference',
        'transfer_memo',
        'internal_notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'pay_year' => 'integer',
            'pay_month' => 'integer',
            'gross_amount' => 'decimal:2',
            'deductions' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
