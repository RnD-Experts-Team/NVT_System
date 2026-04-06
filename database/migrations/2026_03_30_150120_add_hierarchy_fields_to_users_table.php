<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nickname', 100)->nullable()->after('name');
            $table->foreignId('department_id')->after('password')->constrained('departments');
            $table->foreignId('user_level_id')->after('department_id')->constrained('user_levels');
            $table->foreignId('user_level_tier_id')->nullable()->after('user_level_id')->constrained('user_level_tiers')->nullOnDelete();
            $table->boolean('is_admin')->default(false)->after('user_level_tier_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropForeign(['user_level_id']);
            $table->dropForeign(['user_level_tier_id']);
            $table->dropColumn(['nickname', 'department_id', 'user_level_id', 'user_level_tier_id', 'is_admin']);
        });
    }
};
