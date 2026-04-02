<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For SQLite, we need to recreate the table
        // Check if using SQLite
        if (config('database.default') === 'sqlite') {
            // SQLite doesn't support dropping columns, so we'll add columns if they don't exist
            Schema::table('staffs', function (Blueprint $table) {
                // Add new columns only if they don't exist
                if (!Schema::hasColumn('staffs', 'first_name')) {
                    $table->string('first_name')->nullable()->after('id');
                }
                if (!Schema::hasColumn('staffs', 'last_name')) {
                    $table->string('last_name')->nullable()->after('first_name');
                }
                if (!Schema::hasColumn('staffs', 'nic_number')) {
                    $table->string('nic_number')->nullable()->after('last_name');
                }
                if (!Schema::hasColumn('staffs', 'date_of_birth')) {
                    $table->date('date_of_birth')->nullable()->after('nic_number');
                }
                if (!Schema::hasColumn('staffs', 'address')) {
                    $table->text('address')->nullable()->after('date_of_birth');
                }
                if (!Schema::hasColumn('staffs', 'gender')) {
                    $table->string('gender')->default('male')->after('address');
                }
                if (!Schema::hasColumn('staffs', 'blood_group')) {
                    $table->string('blood_group')->nullable()->after('gender');
                }
                if (!Schema::hasColumn('staffs', 'school_name')) {
                    $table->string('school_name')->nullable()->after('blood_group');
                }
                if (!Schema::hasColumn('staffs', 'qualifications')) {
                    $table->text('qualifications')->nullable()->after('school_name');
                }
                if (!Schema::hasColumn('staffs', 'secondary_phone')) {
                    $table->string('secondary_phone')->nullable()->after('qualifications');
                }
                if (!Schema::hasColumn('staffs', 'secondary_phone_has_whatsapp')) {
                    $table->boolean('secondary_phone_has_whatsapp')->default(false)->after('secondary_phone');
                }
                if (!Schema::hasColumn('staffs', 'medical_notes')) {
                    $table->text('medical_notes')->nullable()->after('secondary_phone_has_whatsapp');
                }
                if (!Schema::hasColumn('staffs', 'image_path')) {
                    $table->string('image_path')->nullable()->after('medical_notes');
                }
                if (!Schema::hasColumn('staffs', 'status')) {
                    $table->string('status')->default('active')->after('image_path');
                }
                if (!Schema::hasColumn('staffs', 'deleted_at')) {
                    $table->timestamp('deleted_at')->nullable()->after('updated_at');
                }
            });

            // Add unique constraint for nic_number if it doesn't exist
            try {
                DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS staffs_nic_number_unique ON staffs(nic_number) WHERE nic_number IS NOT NULL');
            } catch (\Exception $e) {
                // Index might already exist, ignore
            }
        } else {
            // For other databases (MySQL, PostgreSQL)
            Schema::table('staffs', function (Blueprint $table) {
                // Drop old columns if they exist
                if (Schema::hasColumn('staffs', 'name')) {
                    $table->dropColumn('name');
                }
                if (Schema::hasColumn('staffs', 'email')) {
                    $table->dropColumn('email');
                }
                if (Schema::hasColumn('staffs', 'phone')) {
                    $table->dropColumn('phone');
                }
                if (Schema::hasColumn('staffs', 'department')) {
                    $table->dropColumn('department');
                }

                // Add new columns only if they don't exist (for fresh migrations, they may already exist)
                if (!Schema::hasColumn('staffs', 'first_name')) {
                    $table->string('first_name')->after('id');
                }
                if (!Schema::hasColumn('staffs', 'last_name')) {
                    $table->string('last_name')->after('first_name');
                }
                if (!Schema::hasColumn('staffs', 'nic_number')) {
                    $table->string('nic_number')->unique()->nullable()->after('last_name');
                }
                if (!Schema::hasColumn('staffs', 'date_of_birth')) {
                    $table->date('date_of_birth')->after('nic_number');
                }
                if (!Schema::hasColumn('staffs', 'address')) {
                    $table->text('address')->after('date_of_birth');
                }
                if (!Schema::hasColumn('staffs', 'gender')) {
                    $table->enum('gender', ['male', 'female', 'other'])->default('male')->after('address');
                }
                if (!Schema::hasColumn('staffs', 'blood_group')) {
                    $table->string('blood_group')->nullable()->after('gender');
                }
                if (!Schema::hasColumn('staffs', 'school_name')) {
                    $table->string('school_name')->nullable()->after('blood_group');
                }
                if (!Schema::hasColumn('staffs', 'qualifications')) {
                    $table->text('qualifications')->nullable()->after('school_name');
                }
                if (!Schema::hasColumn('staffs', 'secondary_phone')) {
                    $table->string('secondary_phone')->after('qualifications');
                }
                if (!Schema::hasColumn('staffs', 'secondary_phone_has_whatsapp')) {
                    $table->boolean('secondary_phone_has_whatsapp')->default(false)->after('secondary_phone');
                }
                if (!Schema::hasColumn('staffs', 'medical_notes')) {
                    $table->text('medical_notes')->nullable()->after('secondary_phone_has_whatsapp');
                }
                if (!Schema::hasColumn('staffs', 'image_path')) {
                    $table->string('image_path')->nullable()->after('medical_notes');
                }
                if (!Schema::hasColumn('staffs', 'status')) {
                    $table->enum('status', ['active', 'inactive', 'on_leave', 'terminated'])->default('active')->after('image_path');
                }
                if (!Schema::hasColumn('staffs', 'deleted_at')) {
                    $table->softDeletes()->after('updated_at');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // For SQLite, we can't easily drop columns, so we'll leave them
        if (config('database.default') !== 'sqlite') {
            Schema::table('staffs', function (Blueprint $table) {
                // Remove new columns
                $columns = [
                    'first_name', 'last_name', 'nic_number', 'date_of_birth', 'address',
                    'gender', 'blood_group', 'school_name', 'qualifications',
                    'secondary_phone', 'secondary_phone_has_whatsapp', 'medical_notes',
                    'image_path', 'status', 'deleted_at'
                ];
                
                foreach ($columns as $column) {
                    if (Schema::hasColumn('staffs', $column)) {
                        $table->dropColumn($column);
                    }
                }

                // Restore old columns
                $table->string('name')->after('id');
                $table->string('email')->unique()->after('name');
                $table->string('phone')->nullable()->after('email');
                $table->string('department')->nullable()->after('phone');
            });
        }
    }
};
