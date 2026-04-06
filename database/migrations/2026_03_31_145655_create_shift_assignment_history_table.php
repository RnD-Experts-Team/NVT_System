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
        Schema::create('shift_assignment_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shift_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->constrained('users')->cascadeOnDelete();
            $table->string('previous_type')->nullable();
            $table->foreignId('previous_shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->string('new_type')->nullable();
            $table->foreignId('new_shift_id')->nullable()->constrained('shifts')->nullOnDelete();
            $table->text('comment')->nullable();
            $table->timestamp('changed_at')->useCurrent();
            // Append-only: no updated_at
            $table->timestamp('created_at')->useCurrent();

            $table->index('shift_assignment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_assignment_history');
    }
};
