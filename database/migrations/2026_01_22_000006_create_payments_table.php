<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('module_id')->nullable()->constrained('modules')->onDelete('set null');
            $table->decimal('amount', 10, 2); // Total amount due
            $table->decimal('discount_amount', 10, 2)->default(0.00); // Discount applied
            $table->decimal('paid_amount', 10, 2); // Actual amount paid
            $table->enum('payment_method', ['cash', 'card'])->default('cash');
            $table->date('payment_date');
            $table->string('month'); // Format: YYYY-MM (e.g., 2026-01)
            $table->year('year');
            $table->enum('status', ['pending', 'paid', 'partial'])->default('paid');
            $table->text('notes')->nullable();
            $table->string('receipt_number')->unique()->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
