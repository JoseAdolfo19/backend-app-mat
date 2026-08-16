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
        Schema::create('academic_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id'); // docente o admin que crea
            $table->uuid('course_id')->nullable(); // lección/curso asociado
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('type')->default('activity'); // activity|exam|holiday|meeting
            $table->string('color')->nullable();
            $table->boolean('all_day')->default(false);
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->index('user_id');
            $table->index('start_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_events');
    }
};
