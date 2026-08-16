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
        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name_es');
            $table->string('name_en');
            $table->string('name_qu')->nullable();
            $table->string('description_es');
            $table->string('description_en');
            $table->string('description_qu')->nullable();
            $table->string('icon');
            $table->unsignedInteger('xp_reward')->default(0);
            $table->string('category')->default('general');
            $table->json('criteria')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};
