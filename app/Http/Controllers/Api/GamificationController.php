<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GamificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GamificationController extends Controller
{
    private GamificationService $service;

    public function __construct(GamificationService $service)
    {
        $this->service = $service;
    }

    /**
     * Resumen completo de gamificación del estudiante autenticado.
     */
    public function summary(Request $request)
    {
        $locale = in_array($request->input('locale'), ['es', 'en', 'qu']) ? $request->input('locale') : 'es';

        return response()->json([
            'gamification' => $this->service->gamificationSummary(Auth::user(), $locale),
        ]);
    }

    /**
     * Sincronizar la definición de logros con la BD (admin).
     */
    public function sync()
    {
        GamificationService::syncDefinitions();

        return response()->json([
            'message' => __('achievements_synced'),
            'count' => count(GamificationService::definitions()),
        ]);
    }

    /**
     * Re-evaluar logros del usuario autenticado.
     */
    public function check()
    {
        $newly = $this->service->checkAchievements(Auth::user());

        return response()->json([
            'new_unlocked' => array_map(fn ($a) => $a->slug, $newly),
        ]);
    }
}