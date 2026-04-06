<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fingerprint_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imported_by')->constrained('users')->restrictOnDelete();
            $table->string('department_id')->nullable();
            $table->date('week_start');
            $table->string('filename');
            $table->string('status')->default('pending'); // pending, processed, failed
            $table->unsignedInteger('rows_imported')->default(0);
            $table->unsignedInteger('rows_failed')->default(0);
            $table->text('error_log')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->index('week_start');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fingerprint_imports');
    }
};
