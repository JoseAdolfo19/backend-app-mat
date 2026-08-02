<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('method', 10);
            $table->string('path');
            $table->string('ip_address', 45);
            $table->string('user_agent', 500)->nullable();
            $table->string('platform', 20)->default('unknown');
            $table->smallInteger('status_code');
            $table->timestamps();

            $table->index('user_id');
            $table->index('created_at');
            $table->index(['method', 'status_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
