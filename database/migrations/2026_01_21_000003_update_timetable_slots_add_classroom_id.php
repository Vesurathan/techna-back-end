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
        Schema::table('timetable_slots', function (Blueprint $table) {
            // Add classroom_id column
            $table->foreignId('classroom_id')->nullable()->after('staff_id')->constrained('classrooms')->onDelete('restrict');
            
            // Keep classroom as string for backward compatibility, but make it nullable
            // We'll migrate data and then can remove this column in a future migration
            $table->string('classroom')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('timetable_slots', function (Blueprint $table) {
            $table->dropForeign(['classroom_id']);
            $table->dropColumn('classroom_id');
            $table->string('classroom')->nullable(false)->change();
        });
    }
};
