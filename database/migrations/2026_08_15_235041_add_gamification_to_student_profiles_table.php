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
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('xp')->default(0)->after('last_activity_date');
            $table->unsignedBigInteger('total_xp')->default(0)->after('xp');
            $table->unsignedInteger('level')->default(1)->after('total_xp');
            $table->unsignedInteger('rank_points')->default(0)->after('level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_profiles', function (Blueprint $table) {
            $table->dropColumn(['xp', 'total_xp', 'level', 'rank_points']);
        });
    }
};
