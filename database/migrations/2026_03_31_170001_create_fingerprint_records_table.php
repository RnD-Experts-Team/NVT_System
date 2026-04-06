<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fingerprint_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('fingerprint_imports')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->date('record_date');
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->timestamps();

            // One record per user per date — re-import upserts
            $table->unique(['user_id', 'record_date']);
            $table->index('import_id');
            $table->index('record_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fingerprint_records');
    }
};
