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
        Schema::create('staffs', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('nic_number')->unique()->nullable();
            $table->date('date_of_birth');
            $table->text('address');
            $table->enum('gender', ['male', 'female', 'other'])->default('male');
            $table->string('blood_group')->nullable();
            $table->string('school_name')->nullable();
            $table->text('qualifications')->nullable();
            $table->string('secondary_phone');
            $table->boolean('secondary_phone_has_whatsapp')->default(false);
            $table->text('medical_notes')->nullable();
            $table->string('image_path')->nullable(); // DigitalOcean S3 path
            $table->enum('status', ['active', 'inactive', 'on_leave', 'terminated'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staffs');
    }
};
