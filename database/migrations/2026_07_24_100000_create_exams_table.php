<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->uuid('teacher_id')->constrained('users');
            $table->string('unit')->nullable();
            $table->string('difficulty')->default('basic');
            $table->integer('time_limit')->nullable();
            $table->integer('max_attempts')->default(1);
            $table->boolean('auto_correct')->default(true);
            $table->boolean('randomize_questions')->default(false);
            $table->boolean('is_active')->default(false);
            $table->boolean('is_published')->default(false);
            $table->integer('total_questions')->default(0);
            $table->integer('total_points')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['teacher_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
