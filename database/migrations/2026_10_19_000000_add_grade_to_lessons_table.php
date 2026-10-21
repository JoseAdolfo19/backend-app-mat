<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Grado escolar al que pertenece la lección (1ro..5to). Los estudiantes
     * solo ven las lecciones de su propio grado; los docentes y administradores
     * ven todo lo suyo con normalidad.
     */
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->string('grade', 20)->nullable()->index()->after('course_id');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropIndex(['grade']);
            $table->dropColumn('grade');
        });
    }
};
