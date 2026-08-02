<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('lesson_id');
            $table->index('status');
        });

        Schema::table('evaluation_results', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('evaluation_id');
            $table->index('status');
            $table->index(['user_id', 'evaluation_id']);
        });

        Schema::table('student_answers', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('evaluation_result_id');
            $table->index('question_id');
        });
    }

    public function down(): void
    {
        Schema::table('lesson_progress', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['lesson_id']);
            $table->dropIndex(['status']);
        });

        Schema::table('evaluation_results', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['evaluation_id']);
            $table->dropIndex(['status']);
            $table->dropIndex(['user_id', 'evaluation_id']);
        });

        Schema::table('student_answers', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['evaluation_result_id']);
            $table->dropIndex(['question_id']);
        });
    }
};
