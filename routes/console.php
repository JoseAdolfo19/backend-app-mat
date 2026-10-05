<?php

use Illuminate\Support\Facades\Schedule;
use App\Models\DeviceToken;

// Prune expired Sanctum tokens (older than 7 days)
Schedule::command('sanctum:prune')->daily();

// Cleanup stale device tokens (inactive for 90 days)
Schedule::call(function () {
    DeviceToken::where('last_used_at', '<', now()->subDays(90))->delete();
})->daily()->at('02:00');

// Respaldo automático de la base de datos (diario) + limpieza de respaldos viejos
Schedule::command('mentematica:backup --prune')->daily()->at('03:00');
