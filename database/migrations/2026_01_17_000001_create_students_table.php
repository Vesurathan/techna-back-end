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
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->string('admission_number')->unique();
            $table->string('barcode')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->date('date_of_birth');
            $table->string('nic_number')->unique()->nullable();
            $table->string('personal_phone');
            $table->string('parent_phone');
            $table->boolean('personal_phone_has_whatsapp')->default(false);
            $table->boolean('parent_phone_has_whatsapp')->default(false);
            $table->string('admission_batch');
            $table->text('address');
            $table->string('school_name')->nullable();
            $table->string('blood_group')->nullable();
            $table->text('medical_notes')->nullable();
            $table->string('image_path')->nullable(); // DigitalOcean S3 path
            $table->decimal('admission_fee', 10, 2)->default(500.00);
            $table->decimal('module_total_amount', 10, 2)->default(0.00);
            $table->decimal('paid_amount', 10, 2)->default(0.00);
            $table->enum('payment_type', ['full', 'admission_only'])->default('admission_only');
            $table->enum('status', ['active', 'inactive', 'graduated', 'suspended'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        // Pivot table for student-module relationship
        Schema::create('module_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->foreignId('module_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['student_id', 'module_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_student');
        Schema::dropIfExists('students');
    }
};