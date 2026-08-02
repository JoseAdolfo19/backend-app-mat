<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('exam_id')->constrained('exams');
            $table->uuid('student_id')->constrained('users');
            $table->string('status')->default('in_progress');
            $table->integer('score')->nullable();
            $table->integer('total_points')->default(0);
            $table->json('answers')->nullable();
            $table->integer('time_spent')->default(0);
            $table->integer('tab_switch_count')->default(0);
            $table->json('cheat_log')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['exam_id', 'student_id']);
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_attempts');
    }
};
