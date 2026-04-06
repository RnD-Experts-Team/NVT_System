<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_level_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_level_id')->constrained('user_levels')->cascadeOnDelete();
            $table->string('tier_name', 100);
            $table->integer('tier_order');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_level_tiers');
    }
};
