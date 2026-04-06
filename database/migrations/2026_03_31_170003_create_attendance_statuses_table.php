<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('fingerprint_record_id')->nullable()->constrained('fingerprint_records')->nullOnDelete();
            $table->foreignId('shift_assignment_id')->nullable()->constrained('shift_assignments')->nullOnDelete();
            $table->date('attendance_date');
            $table->string('status'); // on_time, late, left_early_standard, left_early_early, combined, absent, off
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->unsignedSmallInteger('late_minutes')->nullable();      // minutes after shift_start + grace
            $table->unsignedSmallInteger('early_minutes')->nullable();     // minutes before shift_end
            $table->timestamps();

            // One status per user per date — upserted on every recompute
            $table->unique(['user_id', 'attendance_date'], 'attendance_statuses_uid_date_unique');
            $table->index('attendance_date');
            $table->index('status');
            $table->index('shift_assignment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_statuses');
    }
};
