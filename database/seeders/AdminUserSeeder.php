<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole = Role::where('name', Role::ADMIN)->first();

        User::updateOrCreate(
            ['email' => 'admin@mathflow.com'],
            [
                'id' => Str::uuid(),
                'full_name' => 'Administrador MathFlow',
                'password' => Hash::make('admin123456'),
                'role_id' => $adminRole->id,
                'is_active' => true,
                'provider' => 'email'
            ]
        );
    }
}