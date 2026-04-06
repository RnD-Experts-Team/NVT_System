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
        Schema::create('shift_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekly_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('assignment_date');
            $table->enum('assignment_type', ['shift', 'day_off', 'sick_day', 'leave_request']);
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_cover')->default(false);
            $table->foreignId('cover_for_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cover_shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['weekly_schedule_id', 'user_id', 'assignment_date'], 'sa_schedule_user_date_unique');
            $table->index('user_id');
            $table->index('assignment_date');
            $table->index('shift_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_assignments');
    }
};
