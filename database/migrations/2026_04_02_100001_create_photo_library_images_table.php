<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_library_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('photo_folder_id')->constrained('photo_folders')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_library_images');
    }
};
