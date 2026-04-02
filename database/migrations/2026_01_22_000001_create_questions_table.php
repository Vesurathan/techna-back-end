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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->text('question_text');
            $table->enum('question_type', ['short_answer', 'long_answer', 'single_select', 'multi_select', 'true_false']);
            $table->enum('source', ['module', 'general'])->default('general');
            $table->foreignId('module_id')->nullable()->constrained('modules')->onDelete('cascade');
            $table->string('category');
            $table->text('correct_answer')->nullable(); // For short/long answer questions
            $table->string('image_url')->nullable(); // Question image
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->nullable();
            $table->unsignedInteger('points')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
