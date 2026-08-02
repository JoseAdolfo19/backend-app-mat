<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\StudentProfile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Http\Request;
use Google\Client as GoogleClient;

class GoogleAuthController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();

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
                    return redirect(config('app.url') . '/login?error=inactive');
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

            $token = $user->createToken('auth_token', ['web'])->plainTextToken;

            // Use a short-lived auth code instead of the raw token in URL
            $code = Str::random(32);
            Cache::put('google_auth_code:' . $code, $token, now()->addMinutes(5));

            return redirect(config('app.url') . '/login?auth_code=' . $code);
        } catch (\Exception $e) {
            report($e);
            return redirect(config('app.url') . '/login?error=auth_failed');
        }
    }

    /**
     * Login con Google ID token — para app móvil
     */
    public function loginWithGoogleToken(Request $request)
    {
        $request->validate([
            'access_token' => 'required|string'
        ]);

        try {
            $client = new GoogleClient(['client_id' => config('services.google.client_id')]);
            $payload = $client->verifyIdToken($request->access_token);
            if (!$payload) {
                return response()->json(['message' => __('google_invalid_token')], 401);
            }

            $googleId = $payload['sub'];
            $email = $payload['email'];
            $name = $payload['name'];
            $avatar = $payload['picture'] ?? null;

            $user = User::where('google_id', $googleId)
                ->orWhere('email', $email)
                ->first();

            if ($user) {
                if (!$user->google_id) {
                    $user->update([
                        'google_id' => $googleId,
                        'provider' => 'google'
                    ]);
                }
                if (!$user->is_active) {
                    return response()->json(['message' => __('google_user_inactive_contact_admin')], 403);
                }
                $user->update(['last_login' => now()]);
            } else {
                $studentRole = Role::where('name', Role::STUDENT)->first();
                $user = User::create([
                    'id' => Str::uuid(),
                    'email' => $email,
                    'full_name' => $name,
                    'profile_image' => $avatar,
                    'google_id' => $googleId,
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

            // Detectar plataforma desde header
            $platform = $this->resolvePlatform($request);
            $token = $user->createToken('auth_token', [$platform])->plainTextToken;

            return response()->json([
                'message' => __('google_auth_success'),
                'user' => $user->load('role'),
                'access_token' => $token,
                'token_type' => 'Bearer'
            ]);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'message' => __('google_auth_error')
            ], 500);
        }
    }

    private function resolvePlatform(Request $request): string
    {
        $header = $request->header('X-Platform');
        if ($header && in_array(strtolower($header), ['web', 'android', 'ios'])) {
            return strtolower($header);
        }

        $ua = strtolower($request->userAgent() ?? '');
        if (str_contains($ua, 'android')) {
            return 'android';
        }
        if (str_contains($ua, 'iphone') || str_contains($ua, 'ipad') || str_contains($ua, 'ipod')) {
            return 'ios';
        }

        return 'web';
    }

    /**
     * Exchange a short-lived auth code for the actual token (web callback flow)
     */
    public function exchangeCode(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $cacheKey = 'google_auth_code:' . $request->code;
        $token = Cache::pull($cacheKey);

        if (!$token) {
            return response()->json(['message' => __('code_invalid_or_expired')], 401);
        }

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer'
        ]);
    }
}
