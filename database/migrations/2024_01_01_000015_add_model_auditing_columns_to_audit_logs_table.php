<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            if (DB::getDriverName() === 'mysql') {
                $table->string('action')->after('user_id')->index();
                $table->string('auditable_type')->after('action')->index();
                $table->uuid('auditable_id')->after('auditable_type')->nullable();
                $table->json('old_values')->after('auditable_id')->nullable();
                $table->json('new_values')->after('old_values')->nullable();
            } else {
                $table->string('action')->index();
                $table->string('auditable_type')->index();
                $table->uuid('auditable_id')->nullable();
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['action', 'auditable_type', 'auditable_id', 'old_values', 'new_values']);
        });
    }
};
