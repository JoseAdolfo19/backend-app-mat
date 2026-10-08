<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Models\Lesson;
use App\Models\Evaluation;
use App\Policies\LessonPolicy;
use App\Policies\EvaluationPolicy;

/** Registra las políticas de autorización de lecciones y evaluaciones. */
class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Lesson::class => LessonPolicy::class,
        Evaluation::class => EvaluationPolicy::class,
    ];

    /** Registra en Laravel las políticas declaradas por el proveedor. */
    public function boot(): void
    {
        $this->registerPolicies();
    }
}
