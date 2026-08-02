<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Models\Lesson;
use App\Models\Evaluation;
use App\Policies\LessonPolicy;
use App\Policies\EvaluationPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Lesson::class => LessonPolicy::class,
        Evaluation::class => EvaluationPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
