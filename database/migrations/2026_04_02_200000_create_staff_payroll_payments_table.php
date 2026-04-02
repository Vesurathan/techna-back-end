<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_payroll_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staffs')->cascadeOnDelete();
            $table->unsignedSmallInteger('pay_year');
            $table->unsignedTinyInteger('pay_month');
            $table->string('pay_type', 32);
            $table->decimal('gross_amount', 12, 2)->nullable();
            $table->decimal('deductions', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2);
            $table->date('payment_date');
            $table->string('payment_method', 24);
            $table->string('account_holder_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('iban_swift')->nullable();
            $table->string('transfer_reference')->nullable();
            $table->text('transfer_memo')->nullable();
            $table->text('internal_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['pay_year', 'pay_month']);
            $table->index(['staff_id', 'pay_year', 'pay_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_payroll_payments');
    }
};
