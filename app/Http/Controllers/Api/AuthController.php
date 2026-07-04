<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    /**
     * Registro tradicional (email + password)
     */
    public function register(RegisterRequest $request)
    {
        $validated = $request->validated();

        // Verificar si el email ya existe
        if (User::where('email', $validated['email'])->exists()) {
            return response()->json([
                'message' => 'El correo electrónico ya está registrado'
            ], 422);
        }

        // Obtener rol
        $roleName = $validated['role'] ?? Role::STUDENT;
        $role = Role::where('name', $roleName)->first();

        if (!$role) {
            return response()->json([
                'message' => 'Rol no válido'
            ], 422);
        }

        // Crear usuario
        $user = User::create([
            'id' => Str::uuid(),
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'full_name' => $validated['full_name'],
            'role_id' => $role->id,
            'institution' => $validated['institution'] ?? null,
            'grade' => $validated['grade'] ?? null,
            'is_active' => true,
            'provider' => 'email'
        ]);

        // Crear perfil según el rol
        if ($roleName === Role::STUDENT) {
            StudentProfile::create([
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'academic_level' => $validated['academic_level'] ?? 'basic'
            ]);
        } elseif ($roleName === Role::TEACHER) {
            TeacherProfile::create([
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'department' => $validated['department'] ?? null,
                'specialization' => $validated['specialization'] ?? null
            ]);
        }

        // Generar token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Usuario registrado exitosamente',
            'user' => $user->load('role'),
            'access_token' => $token,
            'token_type' => 'Bearer'
        ], 201);
    }

    /**
     * Login tradicional (email + password)
     */
    public function login(LoginRequest $request)
    {
        $validated = $request->validated();

        // Buscar usuario por email
        $user = User::where('email', $validated['email'])->first();

        if (!$user) {
            return response()->json([
                'message' => 'Credenciales inválidas'
            ], 401);
        }

        // Verificar contraseña
        if (!Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Credenciales inválidas'
            ], 401);
        }

        // Verificar si el usuario está activo
        if (!$user->is_active) {
            return response()->json([
                'message' => 'Usuario inactivo'
            ], 403);
        }

        // Actualizar último login
        $user->update(['last_login' => now()]);

        // Generar token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login exitoso',
            'user' => $user->load('role'),
            'access_token' => $token,
            'token_type' => 'Bearer'
        ]);
    }

    /**
     * Obtener perfil del usuario autenticado
     */
    public function profile()
    {
        $user = Auth::user();
        $user->load(['role', 'studentProfile', 'teacherProfile']);
        
        return response()->json([
            'user' => $user
        ]);
    }
 
    /**
     * Actualizar perfil
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'full_name' => 'sometimes|string|max:255',
            'institution' => 'nullable|string|max:255',
            'grade' => 'nullable|string|max:50',
            'profile_image' => 'nullable|string|url'
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Perfil actualizado exitosamente',
            'user' => $user
        ]);
    }

    /**
     * Cambiar contraseña
     */
    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed'
        ]);

        $user = Auth::user();

        // Verificar si el usuario se registró con Google
        if ($user->isGoogleUser()) {
            return response()->json([
                'message' => 'Los usuarios de Google no pueden cambiar su contraseña aquí. Usa "Olvidé mi contraseña" si es necesario.'
            ], 400);
        }

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Contraseña actual incorrecta'
            ], 400);
        }

        $user->update([
            'password' => Hash::make($validated['new_password'])
        ]);

        return response()->json([
            'message' => 'Contraseña actualizada exitosamente'
        ]);
    }

    /**
     * Vincular cuenta de Google a un usuario existente
     */
    public function connectGoogle(Request $request)
    {
        $request->validate([
            'access_token' => 'required|string'
        ]);

        $user = Auth::user();

        try {
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->userFromToken($request->access_token);

            // Verificar si el Google ID ya está vinculado a otra cuenta
            $existingUser = User::where('google_id', $googleUser->id)
                ->where('id', '!=', $user->id)
                ->first();

            if ($existingUser) {
                return response()->json([
                    'message' => 'Esta cuenta de Google ya está vinculada a otro usuario'
                ], 409);
            }

            $user->update([
                'google_id' => $googleUser->id,
                'google_token' => $googleUser->token,
                'provider' => 'google'
            ]);

            return response()->json([
                'message' => 'Cuenta de Google vinculada exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al vincular cuenta de Google',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Desvincular cuenta de Google
     */
    public function disconnectGoogle()
    {
        $user = Auth::user();

        // Verificar si el usuario tiene contraseña establecida
        if (!$user->password) {
            return response()->json([
                'message' => 'No puedes desvincular tu cuenta de Google sin tener una contraseña establecida. Usa "Olvidé mi contraseña" para crear una.'
            ], 400);
        }

        $user->update([
            'google_id' => null,
            'google_token' => null,
            'provider' => 'email'
        ]);

        return response()->json([
            'message' => 'Cuenta de Google desvinculada exitosamente'
        ]);
    }

    /**
     * Cerrar sesión
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada exitosamente'
        ]);
    }

    /**
     * Refrescar token
     */
    public function refreshToken(Request $request)
    {
        $request->user()->tokens()->delete();
        $token = $request->user()->createToken('auth_token')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer'
        ]);
    }
}