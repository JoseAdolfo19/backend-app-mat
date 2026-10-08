<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Str;

/**
 * Garantiza la existencia de la cuenta administrativa inicial de Aulamate.
 */
class AdminUserSeeder extends Seeder
{
    /**
     * Crea o actualiza el usuario administrador predeterminado y su rol.
     */
    public function run(): void
    {
        $adminRole = Role::where('name', Role::ADMIN)->first();

        User::updateOrCreate(
            ['email' => 'admin@mathflow.com'],
            [
                'id' => Str::uuid(),
                'full_name' => 'Administrador Aulamate',
                'password' => 'admin123456',
                'role_id' => $adminRole->id,
                'is_active' => true,
                'provider' => 'email'
            ]
        );
    }
}