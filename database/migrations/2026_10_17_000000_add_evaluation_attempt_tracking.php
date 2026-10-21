<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_configs', function (Blueprint $table) {
            $table->boolean('allow_tab_switches')->default(true);
        });

        Schema::table('evaluation_results', function (Blueprint $table) {
            $table->unsignedInteger('tab_switch_count')->default(0);
        });

        Schema::table('student_answers', function (Blueprint $table) {
            $table->decimal('points_earned', 8, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('student_answers', function (Blueprint $table) {
            $table->integer('points_earned')->default(0)->change();
        });

        Schema::table('evaluation_results', function (Blueprint $table) {
            $table->dropColumn('tab_switch_count');
        });

        Schema::table('institution_configs', function (Blueprint $table) {
            $table->dropColumn('allow_tab_switches');
        });
    }
};
