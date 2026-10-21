<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las URLs de logos pueden superar los 255 caracteres (p. ej. URLs de
     * imágenes con parámetros de tracking), lo que provocaba el error
     * "Data too long for column 'logo'" al guardar la configuración.
     */
    public function up(): void
    {
        Schema::table('institution_configs', function (Blueprint $table) {
            $table->text('logo')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('institution_configs', function (Blueprint $table) {
            $table->string('logo')->nullable()->change();
        });
    }
};
