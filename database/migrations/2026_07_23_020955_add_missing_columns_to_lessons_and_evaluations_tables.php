<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('is_published');
            $table->integer('views_count')->default(0)->after('order');
        });

        Schema::table('evaluations', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable()->after('is_published');
            $table->integer('total_questions')->default(0)->after('max_attempts');
            $table->integer('total_points')->default(0)->after('total_questions');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn(['published_at', 'views_count']);
        });

        Schema::table('evaluations', function (Blueprint $table) {
            $table->dropColumn(['published_at', 'total_questions', 'total_points']);
        });
    }
};
