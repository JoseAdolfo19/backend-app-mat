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
        Schema::create('system_translations', function (Blueprint $table) {
            $table->id();
            $table->string('key')->index(); // ruta tipo 'nav.dashboard'
            $table->string('locale', 8)->index(); // es|en|qu
            $table->text('value');
            $table->string('group')->default('frontend')->index(); // frontend|backend
            $table->timestamps();

            $table->unique(['key', 'locale']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_translations');
    }
};
