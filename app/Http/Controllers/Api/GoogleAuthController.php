<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\StudentProfile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Http\Request;

class GoogleAuthController extends Controller
{
    /**
     * Redirige al usuario a Google para autenticación
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')
            ->stateless()
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    /**
     * Maneja el callback de Google
     */
    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
            
            // Buscar usuario por google_id o email
            $user = User::where('google_id', $googleUser->id)
                ->orWhere('email', $googleUser->email)
                ->first();

            if ($user) {
                // Actualizar google_id si no existe
                if (!$user->google_id) {
                    $user->update([
                        'google_id' => $googleUser->id,
                        'google_token' => $googleUser->token,
                        'provider' => 'google'
                    ]);
                }

                // Si el usuario existe pero está inactivo
                if (!$user->is_active) {
                    return response()->json([
                        'message' => 'Usuario inactivo. Contacta al administrador.'
                    ], 403);
                }

                // Actualizar último login
                $user->update(['last_login' => now()]);

            } else {
                // Crear nuevo usuario con Google
                $studentRole = Role::where('name', Role::STUDENT)->first();

                $user = User::create([
                    'id' => Str::uuid(),
                    'email' => $googleUser->email,
                    'full_name' => $googleUser->name,
                    'profile_image' => $googleUser->avatar,
                    'google_id' => $googleUser->id,
                    'google_token' => $googleUser->token,
                    'provider' => 'google',
                    'role_id' => $studentRole->id,
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'password' => Hash::make(Str::random(24))
                ]);

                // Crear perfil de estudiante
                StudentProfile::create([
                    'id' => Str::uuid(),
                    'user_id' => $user->id,
                    'academic_level' => 'basic'
                ]);
            }

            // Generar token de acceso
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Autenticación con Google exitosa',
                'user' => $user->load('role'),
                'access_token' => $token,
                'token_type' => 'Bearer'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al autenticar con Google',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Login con token de Google desde el frontend
     */
    public function loginWithGoogleToken(Request $request)
    {
        $request->validate([
            'access_token' => 'required|string'
        ]);

        try {
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->userFromToken($request->access_token);

            if (!$googleUser) {
                return response()->json([
                    'message' => 'Token de Google inválido'
                ], 401);
            }

            // Buscar o crear usuario (misma lógica que el callback)
            $user = User::where('google_id', $googleUser->id)
                ->orWhere('email', $googleUser->email)
                ->first();

            if ($user) {
                if (!$user->google_id) {
                    $user->update([
                        'google_id' => $googleUser->id,
                        'google_token' => $googleUser->token,
                        'provider' => 'google'
                    ]);
                }

                if (!$user->is_active) {
                    return response()->json([
                        'message' => 'Usuario inactivo'
                    ], 403);
                }

                $user->update(['last_login' => now()]);

            } else {
                $studentRole = Role::where('name', Role::STUDENT)->first();

                $user = User::create([
                    'id' => Str::uuid(),
                    'email' => $googleUser->email,
                    'full_name' => $googleUser->name,
                    'profile_image' => $googleUser->avatar,
                    'google_id' => $googleUser->id,
                    'google_token' => $googleUser->token,
                    'provider' => 'google',
                    'role_id' => $studentRole->id,
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'password' => Hash::make(Str::random(24))
                ]);

                StudentProfile::create([
                    'id' => Str::uuid(),
                    'user_id' => $user->id,
                    'academic_level' => 'basic'
                ]);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Autenticación con Google exitosa',
                'user' => $user->load('role'),
                'access_token' => $token,
                'token_type' => 'Bearer'
            ]); 

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al autenticar con Google',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}