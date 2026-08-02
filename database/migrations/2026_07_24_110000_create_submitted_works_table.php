<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submitted_works', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('student_id')->constrained('users');
            $table->uuid('lesson_id')->nullable()->constrained('lessons');
            $table->uuid('evaluation_id')->nullable()->constrained('evaluations');
            $table->uuid('exam_id')->nullable()->constrained('exams');
            $table->string('work_type');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->integer('score')->nullable();
            $table->integer('max_score')->default(20);
            $table->text('teacher_feedback')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['lesson_id']);
            $table->index(['evaluation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submitted_works');
    }
};
