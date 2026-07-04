<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Lesson;
use App\Models\Evaluation;
use App\Models\AcademicPeriod;
use App\Models\InstitutionConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    // ========== GESTIÓN DE USUARIOS ==========
    
    public function getUsers(Request $request)
    {
        $query = User::with('role');

        if ($request->has('role')) {
            $role = Role::where('name', $request->role)->first();
            if ($role) {
                $query->where('role_id', $role->id);
            }
        }

        if ($request->has('search')) {
            $query->where('full_name', 'LIKE', '%' . $request->search . '%')
                  ->orWhere('email', 'LIKE', '%' . $request->search . '%');
        }

        $users = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($users);
    }

    public function getUser($id)
    {
        $user = User::with(['role', 'studentProfile', 'teacherProfile'])
            ->findOrFail($id);

        return response()->json($user);
    }

    public function createUser(Request $request)
    {
        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,teacher,student',
            'institution' => 'nullable|string',
            'grade' => 'nullable|string'
        ]);

        $role = Role::where('name', $validated['role'])->first();

        $user = User::create([
            'id' => Str::uuid(),
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $role->id,
            'institution' => $validated['institution'] ?? null,
            'grade' => $validated['grade'] ?? null,
            'is_active' => true,
            'provider' => 'email'
        ]);

        return response()->json([
            'message' => 'Usuario creado exitosamente',
            'user' => $user->load('role')
        ], 201);
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'full_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'role' => 'sometimes|in:admin,teacher,student',
            'institution' => 'nullable|string',
            'grade' => 'nullable|string',
            'is_active' => 'sometimes|boolean'
        ]);

        if (isset($validated['role'])) {
            $role = Role::where('name', $validated['role'])->first();
            $validated['role_id'] = $role->id;
            unset($validated['role']);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Usuario actualizado exitosamente',
            'user' => $user->load('role')
        ]);
    }

    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        
        if ($user->isAdmin()) {
            return response()->json([
                'message' => 'No puedes eliminar un usuario administrador'
            ], 403);
        }

        $user->delete();

        return response()->json([
            'message' => 'Usuario eliminado exitosamente'
        ]);
    }

    public function activateUser($id)
    {
        $user = User::findOrFail($id);
        $user->update(['is_active' => true]);

        return response()->json([
            'message' => 'Usuario activado exitosamente'
        ]);
    }

    public function deactivateUser($id)
    {
        $user = User::findOrFail($id);
        
        if ($user->isAdmin()) {
            return response()->json([
                'message' => 'No puedes desactivar un usuario administrador'
            ], 403);
        }

        $user->update(['is_active' => false]);

        return response()->json([
            'message' => 'Usuario desactivado exitosamente'
        ]);
    }

    // ========== CONFIGURACIÓN DEL SISTEMA ==========
    
    public function getConfig()
    {
        $config = InstitutionConfig::first();
        
        if (!$config) {
            $config = InstitutionConfig::create([
                'id' => Str::uuid(),
                'institution_name' => 'MathFlow Education',
                'primary_color' => '#004AC6',
                'secondary_color' => '#006C49'
            ]);
        }

        return response()->json($config);
    }

    public function updateConfig(Request $request)
    {
        $config = InstitutionConfig::first() ?? InstitutionConfig::create([
            'id' => Str::uuid(),
            'institution_name' => 'MathFlow Education'
        ]);

        $validated = $request->validate([
            'institution_name' => 'sometimes|string|max:255',
            'primary_color' => 'sometimes|string|max:7',
            'secondary_color' => 'sometimes|string|max:7',
            'logo' => 'nullable|string',
            'email_notifications' => 'nullable|array',
            'backup_frequency' => 'nullable|string'
        ]);

        $config->update($validated);

        return response()->json([
            'message' => 'Configuración actualizada exitosamente',
            'config' => $config
        ]);
    }

    // ========== PERÍODOS ACADÉMICOS ==========
    
    public function getPeriods()
    {
        $periods = AcademicPeriod::orderBy('start_date', 'desc')->get();
        return response()->json($periods);
    }

    public function createPeriod(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'description' => 'nullable|string'
        ]);

        // Desactivar otros períodos si este es activo
        if ($request->has('is_active') && $request->is_active) {
            AcademicPeriod::where('is_active', true)->update(['is_active' => false]);
        }

        $period = AcademicPeriod::create([
            'id' => Str::uuid(),
            'name' => $validated['name'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->is_active ?? false
        ]);

        return response()->json([
            'message' => 'Período académico creado exitosamente',
            'period' => $period
        ], 201);
    }

    public function updatePeriod(Request $request, $id)
    {
        $period = AcademicPeriod::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'is_active' => 'sometimes|boolean',
            'description' => 'nullable|string'
        ]);

        // Desactivar otros períodos si este se activa
        if (isset($validated['is_active']) && $validated['is_active']) {
            AcademicPeriod::where('is_active', true)
                ->where('id', '!=', $id)
                ->update(['is_active' => false]);
        }

        $period->update($validated);

        return response()->json([
            'message' => 'Período académico actualizado exitosamente',
            'period' => $period
        ]);
    }

    public function deletePeriod($id)
    {
        $period = AcademicPeriod::findOrFail($id);
        $period->delete();

        return response()->json([
            'message' => 'Período académico eliminado exitosamente'
        ]);
    }

    // ========== DASHBOARD ADMIN ==========
    
    public function dashboard()
    {
        $stats = [
            'total_users' => User::count(),
            'total_students' => User::where('role_id', Role::where('name', Role::STUDENT)->first()->id)->count(),
            'total_teachers' => User::where('role_id', Role::where('name', Role::TEACHER)->first()->id)->count(),
            'total_lessons' => Lesson::count(),
            'published_lessons' => Lesson::where('is_published', true)->count(),
            'total_evaluations' => Evaluation::count(),
            'active_period' => AcademicPeriod::where('is_active', true)->first()
        ];

        // Usuarios recientes
        $recentUsers = User::with('role')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'stats' => $stats,
            'recent_users' => $recentUsers
        ]);
    }
}