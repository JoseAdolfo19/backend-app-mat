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

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// ============================================================
// RUTAS PÚBLICAS (No requieren autenticación)
// ============================================================

Route::prefix('auth')->group(function () {
    
    // Autenticación tradicional (email + password)
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    
    // Google OAuth
    Route::get('/google/redirect', [GoogleAuthController::class, 'redirectToGoogle']);
    Route::get('/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);
    Route::post('/google/login', [GoogleAuthController::class, 'loginWithGoogleToken']);
    
    // Recuperación de contraseña
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink']);
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);
});

// ============================================================
// RUTAS PROTEGIDAS (Requieren autenticación con Sanctum)
// ============================================================

Route::middleware(['auth:sanctum'])->group(function () {
    
    // ============================================================
    // PERFIL DE USUARIO (Todos los roles)
    // ============================================================
    
    Route::prefix('user')->group(function () {
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/connect-google', [AuthController::class, 'connectGoogle']);
        Route::post('/disconnect-google', [AuthController::class, 'disconnectGoogle']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
    });
    
    // ============================================================
    // NOTIFICACIONES (Todos los roles)
    // ============================================================
    
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::put('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::put('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
        Route::delete('/read/delete', [NotificationController::class, 'deleteRead']);
    });
    
    // ============================================================
    // DASHBOARDS (Según rol)
    // ============================================================
    
    Route::prefix('dashboard')->group(function () {
        Route::get('/student', [ProgressController::class, 'studentDashboard'])
            ->middleware('role:student');
        Route::get('/teacher', [ProgressController::class, 'teacherDashboard'])
            ->middleware('role:teacher');
        Route::get('/admin', [AdminController::class, 'dashboard'])
            ->middleware('role:admin');
    });
    
    // ============================================================
    // PROGRESO Y ESTADÍSTICAS (Todos los roles)
    // ============================================================
    
    Route::prefix('progress')->group(function () {
        Route::get('/my-stats', [ProgressController::class, 'getMyStats']);
        Route::get('/badges', [ProgressController::class, 'getBadges']);
    });
    
    // ============================================================
    // LECCIONES (Todos los roles)
    // ============================================================
    
    Route::prefix('lessons')->group(function () {
        // Rutas públicas (para estudiantes y docentes)
        Route::get('/', [LessonController::class, 'index']);
        Route::get('/{id}', [LessonController::class, 'show']);
        Route::get('/unit/{unit}', [LessonController::class, 'getByUnit']);
        Route::get('/{id}/resources', [LessonController::class, 'getResources']);
        Route::get('/{id}/progress', [ProgressController::class, 'getLessonProgress']);
        Route::post('/{id}/progress', [ProgressController::class, 'updateLessonProgress']);
        
        // Rutas solo para docentes y admin
        Route::middleware(['role:teacher,admin'])->group(function () {
            Route::post('/', [LessonController::class, 'store']);
            Route::put('/{id}', [LessonController::class, 'update']);
            Route::delete('/{id}', [LessonController::class, 'destroy']);
            Route::post('/{id}/publish', [LessonController::class, 'publish']);
            Route::post('/{id}/unpublish', [LessonController::class, 'unpublish']);
            Route::post('/{id}/duplicate', [LessonController::class, 'duplicate']);
            Route::post('/{id}/resources', [LessonController::class, 'addResource']);
            Route::delete('/{id}/resources/{resourceId}', [LessonController::class, 'removeResource']);
            Route::get('/{id}/stats', [LessonController::class, 'getStats']);
        });
    });
    
    // ============================================================
    // EVALUACIONES (Todos los roles)
    // ============================================================
    
    Route::prefix('evaluations')->group(function () {
        // Rutas públicas (para estudiantes y docentes)
        Route::get('/', [EvaluationController::class, 'index']);
        Route::get('/{id}', [EvaluationController::class, 'show']);
        Route::get('/{evaluationId}/questions', [EvaluationController::class, 'getQuestions']);
        Route::post('/{evaluationId}/submit', [EvaluationController::class, 'submit']);
        Route::get('/{evaluationId}/results', [EvaluationController::class, 'getResults']);
        
        // Rutas para docentes y admin (resultados de estudiantes específicos)
        Route::middleware(['role:teacher,admin'])->group(function () {
            Route::get('/{evaluationId}/result/{userId}', [EvaluationController::class, 'getStudentResult']);
            Route::get('/{id}/stats', [EvaluationController::class, 'getStats']);
        });
        
        // Rutas solo para docentes y admin (gestión)
        Route::middleware(['role:teacher,admin'])->group(function () {
            Route::post('/', [EvaluationController::class, 'store']);
            Route::put('/{id}', [EvaluationController::class, 'update']);
            Route::delete('/{id}', [EvaluationController::class, 'destroy']);
            Route::post('/{id}/publish', [EvaluationController::class, 'publish']);
            Route::post('/{id}/unpublish', [EvaluationController::class, 'unpublish']);
            Route::post('/{id}/duplicate', [EvaluationController::class, 'duplicate']);
            
            // Gestión de preguntas
            Route::post('/{evaluationId}/questions', [EvaluationController::class, 'addQuestion']);
            Route::put('/questions/{questionId}', [EvaluationController::class, 'updateQuestion']);
            Route::delete('/questions/{questionId}', [EvaluationController::class, 'deleteQuestion']);
        });
    });
    
    // ============================================================
    // REPORTES (Docentes y Admin)
    // ============================================================
    
    Route::prefix('reports')->middleware(['role:teacher,admin'])->group(function () {
        Route::get('/performance', [ReportController::class, 'performanceReport']);
        Route::get('/grades', [ReportController::class, 'gradesReport']);
        Route::get('/student/{userId}', [ReportController::class, 'studentReport']);
        Route::get('/export/pdf', [ReportController::class, 'exportPDF']);
        Route::get('/export/excel', [ReportController::class, 'exportExcel']);
    });
    
    // ============================================================
    // ADMINISTRACIÓN (Solo Admin)
    // ============================================================
    
    Route::prefix('admin')->middleware(['role:admin'])->group(function () {
        
        // Dashboard Admin
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        
        // ===== Gestión de Usuarios =====
        Route::prefix('users')->group(function () {
            Route::get('/', [AdminController::class, 'getUsers']);
            Route::get('/{id}', [AdminController::class, 'getUser']);
            Route::post('/', [AdminController::class, 'createUser']);
            Route::put('/{id}', [AdminController::class, 'updateUser']);
            Route::delete('/{id}', [AdminController::class, 'deleteUser']);
            Route::post('/{id}/activate', [AdminController::class, 'activateUser']);
            Route::post('/{id}/deactivate', [AdminController::class, 'deactivateUser']);
            Route::post('/import', [AdminController::class, 'importUsers']);
            Route::get('/export', [AdminController::class, 'exportUsers']);
        });
        
        // ===== Configuración del Sistema =====
        Route::prefix('config')->group(function () {
            Route::get('/', [AdminController::class, 'getConfig']);
            Route::put('/', [AdminController::class, 'updateConfig']);
        });
        
        // ===== Períodos Académicos =====
        Route::prefix('periods')->group(function () {
            Route::get('/', [AdminController::class, 'getPeriods']);
            Route::post('/', [AdminController::class, 'createPeriod']);
            Route::put('/{id}', [AdminController::class, 'updatePeriod']);
            Route::delete('/{id}', [AdminController::class, 'deletePeriod']);
        });
        
        // ===== Copias de Seguridad =====
        Route::prefix('backup')->group(function () {
            Route::post('/', [AdminController::class, 'createBackup']);
            Route::get('/last', [AdminController::class, 'getLastBackup']);
            Route::get('/download/{filename}', [AdminController::class, 'downloadBackup']);
        });
    });
});

// ============================================================
// RUTAS PÚBLICAS (Sin autenticación)
// ============================================================

// Configuración pública de la institución
Route::get('/config', [AdminController::class, 'getConfig']);

// ============================================================
// RUTA DE PRUEBA (Opcional)
// ============================================================

Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'message' => 'MathFlow API is running',
        'timestamp' => now()
    ]);
});