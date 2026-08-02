<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $hasOldColumn = Schema::hasColumn('audit_logs', 'ip');
        if ($hasOldColumn) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->renameColumn('ip', 'ip_address');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->renameColumn('ip_address', 'ip');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('questions', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
