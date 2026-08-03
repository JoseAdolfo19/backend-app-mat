<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\GoogleAuthController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\EvaluationController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\ParentController;
use App\Http\Controllers\Api\GuestStudentController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\SubmittedWorkController;
use App\Http\Controllers\Api\RankingController;

/*
|--------------------------------------------------------------------------
| API Routes — v1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // ============================================================
    // RUTAS PÚBLICAS (No requieren autenticación)
    // ============================================================

    Route::prefix('auth')->group(function () {
        
        // Autenticación tradicional (email + password)
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware('throttle:5,1');
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:10,1');
        
        // Google OAuth
        Route::get('/google/redirect', [GoogleAuthController::class, 'redirectToGoogle'])
            ->middleware('throttle:30,1');
        Route::get('/google/callback', [GoogleAuthController::class, 'handleGoogleCallback'])
            ->middleware('throttle:30,1');
        Route::post('/google/exchange-code', [GoogleAuthController::class, 'exchangeCode'])
            ->middleware('throttle:10,1');
        Route::post('/google/login', [GoogleAuthController::class, 'loginWithGoogleToken'])
            ->middleware('throttle:10,1');
        
        // Recuperación de contraseña
        Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])
            ->middleware('throttle:5,1');
        Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])
            ->middleware('throttle:5,1');
    });

    // ============================================================
    // RUTAS PROTEGIDAS (Requieren autenticación con Sanctum)
    // ============================================================

    Route::middleware(['auth:sanctum', 'auth.active', 'rate.limit'])->group(function () {

        // ============================================================
        // AI CHAT (Proxy seguro - protege la API key de Groq)
        // ============================================================
        Route::post('/ai/chat', [AiController::class, 'chat'])
            ->middleware('throttle:20,1');
        Route::post('/ai/generate-lesson', [AiController::class, 'generateLesson'])
            ->middleware(['role:teacher,admin', 'throttle:10,1']);

        // ============================================================
        // PERFIL DE USUARIO (Todos los roles)
        // ============================================================
        
        Route::prefix('user')->group(function () {
            Route::get('/profile', [AuthController::class, 'profile'])
                ->middleware('cache.api');
            Route::put('/profile', [AuthController::class, 'updateProfile'])
                ->middleware('audit');
            Route::put('/change-password', [AuthController::class, 'changePassword'])
                ->middleware('audit');
            Route::post('/send-verification-email', [AuthController::class, 'sendVerificationEmail'])
                ->middleware('throttle:3,1');
            Route::post('/verify-email', [AuthController::class, 'verifyEmail'])
                ->middleware('throttle:5,1');
            Route::post('/connect-google', [AuthController::class, 'connectGoogle'])
                ->middleware('audit');
            Route::post('/disconnect-google', [AuthController::class, 'disconnectGoogle'])
                ->middleware('audit');
            Route::post('/logout', [AuthController::class, 'logout'])
                ->middleware('audit');
            Route::post('/logout-platform', [AuthController::class, 'logoutPlatform'])
                ->middleware('audit');
            Route::post('/logout-all', [AuthController::class, 'logoutAll'])
                ->middleware('audit');
            Route::get('/devices', [AuthController::class, 'devices'])
                ->middleware('cache.api');
            Route::post('/refresh-token', [AuthController::class, 'refreshToken'])
                ->middleware('audit');
        });
        
        // ============================================================
        // DISPOSITIVOS / PUSH NOTIFICATIONS
        // ============================================================
        
        Route::prefix('devices')->group(function () {
            Route::post('/register', [DeviceController::class, 'register'])
                ->middleware('audit');
            Route::post('/unregister', [DeviceController::class, 'unregister'])
                ->middleware('audit');
            Route::get('/', [DeviceController::class, 'list'])
                ->middleware('cache.api');
        });
        
        // ============================================================
        // NOTIFICACIONES (Todos los roles)
        // ============================================================
        
        Route::prefix('notifications')->group(function () {
            Route::get('/', [NotificationController::class, 'index'])
                ->middleware('cache.api');
            Route::get('/unread-count', [NotificationController::class, 'unreadCount'])
                ->middleware('cache.api');
            Route::put('/read-all', [NotificationController::class, 'markAllAsRead'])
                ->middleware('audit');
            Route::delete('/read/delete', [NotificationController::class, 'deleteRead'])
                ->middleware('audit');
            Route::put('/{id}/read', [NotificationController::class, 'markAsRead'])
                ->middleware('audit');
            Route::delete('/{id}', [NotificationController::class, 'destroy'])
                ->middleware('audit');
        });
        
        // ============================================================
        // DASHBOARDS (Según rol) — con soporte para mobile
        // ============================================================
        
        Route::prefix('dashboard')->group(function () {
            // Dashboard completo (web)
            Route::get('/student', [ProgressController::class, 'studentDashboard'])
                ->middleware(['role:student', 'cache.api']);
            // Dashboard ligero (mobile) — solo stats
            Route::get('/student/stats', [ProgressController::class, 'studentStats'])
                ->middleware(['role:student', 'cache.api']);
            Route::get('/teacher', [ProgressController::class, 'teacherDashboard'])
                ->middleware(['role:teacher', 'cache.api']);
            Route::get('/admin', [AdminController::class, 'dashboard'])
                ->middleware(['role:admin', 'cache.api']);
        });
        
        // ============================================================
        // PROGRESO Y ESTADÍSTICAS (Todos los roles)
        // ============================================================
        
        Route::prefix('progress')->group(function () {
            Route::get('/my-stats', [ProgressController::class, 'getMyStats'])
                ->middleware('cache.api');
            Route::get('/badges', [ProgressController::class, 'getBadges'])
                ->middleware('cache.api');
            Route::get('/level', [ProgressController::class, 'studentLevel'])
                ->middleware('cache.api');
        });
        
        // ============================================================
        // LECCIONES (Todos los roles)
        // ============================================================
        
        Route::prefix('lessons')->group(function () {
            Route::get('/', [LessonController::class, 'index'])
                ->middleware(['cache.api', 'throttle:30,1']);
            Route::get('/recommended', [LessonController::class, 'recommended'])
                ->middleware('cache.api');
            // /unit/{unit} BEFORE /{id} to avoid route collision
            Route::get('/unit/{unit}', [LessonController::class, 'getByUnit'])
                ->middleware('cache.api');
            Route::get('/{id}', [LessonController::class, 'show'])
                ->middleware('cache.api');
            // Contenido completo de lección (separado para mobile)
            Route::get('/{id}/content', [LessonController::class, 'content'])
                ->middleware('cache.api');
            Route::get('/{id}/resources', [LessonController::class, 'getResources'])
                ->middleware('cache.api');
            Route::get('/{id}/progress', [ProgressController::class, 'getLessonProgress'])
                ->middleware('cache.api');
            Route::post('/{id}/progress', [ProgressController::class, 'updateLessonProgress'])
                ->middleware(['role:student', 'audit']);
            
            Route::middleware(['role:teacher'])->group(function () {
                Route::post('/', [LessonController::class, 'store'])
                    ->middleware('audit');
                Route::post('/resources/upload', [LessonController::class, 'uploadResource'])
                    ->middleware(['throttle:30,1', 'audit']);
                Route::put('/{id}', [LessonController::class, 'update'])
                    ->middleware('audit');
                Route::delete('/{id}', [LessonController::class, 'destroy'])
                    ->middleware('audit');
                Route::post('/{id}/publish', [LessonController::class, 'publish'])
                    ->middleware('audit');
                Route::post('/{id}/unpublish', [LessonController::class, 'unpublish'])
                    ->middleware('audit');
                Route::post('/{id}/duplicate', [LessonController::class, 'duplicate'])
                    ->middleware('audit');
                Route::post('/{id}/resources', [LessonController::class, 'addResource'])
                    ->middleware('audit');
                Route::delete('/{id}/resources/{resourceId}', [LessonController::class, 'removeResource'])
                    ->middleware('audit');
                Route::get('/{id}/stats', [LessonController::class, 'getStats'])
                    ->middleware('cache.api');
            });
        });
        
        // ============================================================
        // EVALUACIONES (Todos los roles)
        // ============================================================
        
        Route::prefix('evaluations')->group(function () {
            Route::get('/', [EvaluationController::class, 'index'])
                ->middleware(['cache.api', 'throttle:30,1']);
            Route::get('/adaptive', [EvaluationController::class, 'adaptive'])
                ->middleware('cache.api');
            Route::get('/{id}', [EvaluationController::class, 'show'])
                ->middleware('cache.api');
            Route::get('/{evaluationId}/questions', [EvaluationController::class, 'getQuestions'])
                ->middleware('cache.api');
            Route::post('/{evaluationId}/submit', [EvaluationController::class, 'submit'])
                ->middleware(['role:student', 'audit']);
            Route::get('/{evaluationId}/results', [EvaluationController::class, 'getResults'])
                ->middleware('cache.api');
            
            Route::middleware(['role:teacher,admin'])->group(function () {
                Route::get('/{evaluationId}/result/{userId}', [EvaluationController::class, 'getStudentResult'])
                    ->middleware('cache.api');
                Route::get('/{id}/stats', [EvaluationController::class, 'getStats'])
                    ->middleware('cache.api');
            });
            
            Route::middleware(['role:teacher'])->group(function () {
                Route::post('/', [EvaluationController::class, 'store'])
                    ->middleware('audit');
                Route::put('/{id}', [EvaluationController::class, 'update'])
                    ->middleware('audit');
                Route::delete('/{id}', [EvaluationController::class, 'destroy'])
                    ->middleware('audit');
                Route::post('/{id}/publish', [EvaluationController::class, 'publish'])
                    ->middleware('audit');
                Route::post('/{id}/unpublish', [EvaluationController::class, 'unpublish'])
                    ->middleware('audit');
                Route::post('/{id}/duplicate', [EvaluationController::class, 'duplicate'])
                    ->middleware('audit');
                
                Route::post('/{evaluationId}/questions', [EvaluationController::class, 'addQuestion'])
                    ->middleware('audit');
                Route::put('/questions/{questionId}', [EvaluationController::class, 'updateQuestion'])
                    ->middleware('audit');
                Route::delete('/questions/{questionId}', [EvaluationController::class, 'deleteQuestion'])
                    ->middleware('audit');
            });
        });
        
        // ============================================================
        // EXÁMENES (Teacher-created tests, separate from evaluations)
        // ============================================================

        Route::prefix('exams')->group(function () {
            Route::get('/', [ExamController::class, 'index'])
                ->middleware(['cache.api', 'throttle:30,1']);
            Route::post('/', [ExamController::class, 'store'])
                ->middleware(['role:teacher,admin', 'audit']);
            Route::get('/{id}', [ExamController::class, 'show'])
                ->middleware('cache.api');
            Route::put('/{id}', [ExamController::class, 'update'])
                ->middleware(['role:teacher,admin', 'audit']);
            Route::delete('/{id}', [ExamController::class, 'destroy'])
                ->middleware(['role:teacher,admin', 'audit']);
            Route::post('/{id}/activate', [ExamController::class, 'activate'])
                ->middleware(['role:teacher,admin', 'audit']);
            Route::post('/{id}/deactivate', [ExamController::class, 'deactivate'])
                ->middleware(['role:teacher,admin', 'audit']);
            Route::post('/{id}/start', [ExamController::class, 'startAttempt'])
                ->middleware(['role:student', 'audit']);
            Route::post('/attempts/{attemptId}/submit', [ExamController::class, 'submitAttempt'])
                ->middleware(['role:student', 'audit']);
            Route::post('/attempts/{attemptId}/cheat', [ExamController::class, 'reportCheating'])
                ->middleware(['role:student']);
            Route::get('/{id}/stats', [ExamController::class, 'getExamStats'])
                ->middleware(['role:teacher,admin', 'cache.api']);
        });

        // ============================================================
        // SUBMITTED WORKS (Student work submissions)
        // ============================================================

        Route::get('/submitted-works', [SubmittedWorkController::class, 'index']);
        Route::post('/submitted-works', [SubmittedWorkController::class, 'store']);
        Route::get('/submitted-works/student/summary', [SubmittedWorkController::class, 'studentSummary']);
        Route::get('/submitted-works/{id}', [SubmittedWorkController::class, 'show']);
        Route::post('/submitted-works/{id}/grade', [SubmittedWorkController::class, 'grade'])
            ->middleware('role:teacher,admin');
        Route::post('/submitted-works/{id}/return', [SubmittedWorkController::class, 'returnWork'])
            ->middleware('role:teacher,admin');
        Route::post('/submitted-works/auto-generate', [SubmittedWorkController::class, 'autoGenerateFromCompleted'])
            ->middleware('role:teacher,admin');

        // ============================================================
        // RANKINGS
        // ============================================================

        Route::get('/rankings/course', [RankingController::class, 'courseRanking']);
        Route::get('/rankings/overall', [RankingController::class, 'overallRanking']);
        Route::get('/rankings/my-position', [RankingController::class, 'myPosition']);

        // ============================================================
        // REPORTES (Docentes y Admin)
        // ============================================================
        
        Route::prefix('reports')->middleware(['role:teacher,admin'])->group(function () {
            Route::get('/performance', [ReportController::class, 'performanceReport'])
                ->middleware('cache.api');
            Route::get('/filtered-performance', [ReportController::class, 'filteredPerformanceReport'])
                ->middleware('cache.api');
            Route::get('/student-detail/{studentId}', [ReportController::class, 'studentDetailReport'])
                ->middleware('cache.api');
            Route::get('/course-detail/{unit}', [ReportController::class, 'courseDetailReport'])
                ->middleware('cache.api');
            Route::get('/grades', [ReportController::class, 'gradesReport'])
                ->middleware('cache.api');
            Route::get('/student/{userId}', [ReportController::class, 'studentReport'])
                ->middleware('cache.api');
            Route::get('/participation', [ReportController::class, 'participationReport'])
                ->middleware('cache.api');
            Route::get('/export/pdf', [ReportController::class, 'exportPDF'])
                ->middleware('throttle:5,1');
            Route::get('/export/excel', [ReportController::class, 'exportExcel'])
                ->middleware('throttle:5,1');
            Route::get('/export/performance/pdf', [ReportController::class, 'exportPerformancePDF'])
                ->middleware('throttle:5,1');
            Route::get('/export/performance/excel', [ReportController::class, 'exportPerformanceExcel'])
                ->middleware('throttle:5,1');
            Route::get('/export/student/{studentId}/pdf', [ReportController::class, 'exportStudentReportPDF'])
                ->middleware('throttle:5,1');
            Route::get('/export/student/{studentId}/excel', [ReportController::class, 'exportStudentReportExcel'])
                ->middleware('throttle:5,1');
            Route::get('/export/grades/pdf', [ReportController::class, 'exportGradesPDF'])
                ->middleware('throttle:5,1');
            Route::get('/export/grades/excel', [ReportController::class, 'exportGradesExcel'])
                ->middleware('throttle:5,1');
        });
        
        // ============================================================
        // PADRES DE FAMILIA (Solo Parent)
        // ============================================================

        Route::prefix('parent')->middleware('role:parent')->group(function () {
            Route::get('/children', [ParentController::class, 'index']);
            Route::get('/children/{studentId}/progress', [ParentController::class, 'childProgress']);
            Route::get('/children/{studentId}/report', [ParentController::class, 'childReport']);
        });

        // ============================================================
        // ADMINISTRACIÓN (Solo Admin)
        // ============================================================
        
        Route::prefix('admin')->middleware(['role:admin'])->group(function () {
            
            Route::prefix('users')->group(function () {
                Route::get('/', [AdminController::class, 'getUsers'])
                    ->middleware('cache.api');
                Route::get('/{id}', [AdminController::class, 'getUser'])
                    ->middleware('cache.api');
                Route::post('/', [AdminController::class, 'createUser'])
                    ->middleware('audit');
                Route::put('/{id}', [AdminController::class, 'updateUser'])
                    ->middleware('audit');
                Route::delete('/{id}', [AdminController::class, 'deleteUser'])
                    ->middleware('audit');
                Route::post('/{id}/activate', [AdminController::class, 'activateUser'])
                    ->middleware('audit');
                Route::post('/{id}/deactivate', [AdminController::class, 'deactivateUser'])
                    ->middleware('audit');
                Route::post('/import', [AdminController::class, 'importUsers'])
                    ->middleware('audit');
                Route::get('/export', [AdminController::class, 'exportUsers']);
            });
            
            Route::prefix('config')->group(function () {
                Route::get('/', [AdminController::class, 'getConfig'])
                    ->middleware('cache.api');
                Route::put('/', [AdminController::class, 'updateConfig'])
                    ->middleware('audit');
            });
            
            Route::prefix('periods')->group(function () {
                Route::get('/', [AdminController::class, 'getPeriods'])
                    ->middleware('cache.api');
                Route::post('/', [AdminController::class, 'createPeriod'])
                    ->middleware('audit');
                Route::put('/{id}', [AdminController::class, 'updatePeriod'])
                    ->middleware('audit');
                Route::delete('/{id}', [AdminController::class, 'deletePeriod'])
                    ->middleware('audit');
            });
            
            Route::prefix('backup')->group(function () {
                Route::post('/', [AdminController::class, 'createBackup'])
                    ->middleware('audit');
                Route::get('/last', [AdminController::class, 'getLastBackup'])
                    ->middleware('cache.api');
                Route::get('/download/{filename}', [AdminController::class, 'downloadBackup']);
            });
        });
    });

    // ============================================================
    // GUEST STUDENT LOOKUP (Público)
    // ============================================================

    Route::post('/guest/student-lookup', [GuestStudentController::class, 'lookup']);
    Route::get('/guest/captcha', [GuestStudentController::class, 'generateCaptcha']);

    // ============================================================
    // RUTAS PÚBLICAS (Sin autenticación)
    // ============================================================

    Route::get('/config', [AdminController::class, 'getConfig'])
        ->middleware('throttle:30,1');

    // ============================================================
    // HEALTH CHECK
    // ============================================================

    Route::get('/health', function () {
        return response()->json([
            'status' => 'healthy',
            'message' => 'MathFlow API is running',
            'version' => 'v1',
            'timestamp' => now()
        ]);
    });
});
