<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => Role::ADMIN, 'description' => 'Administrador del sistema'],
            ['name' => Role::TEACHER, 'description' => 'Docente'],
            ['name' => Role::STUDENT, 'description' => 'Estudiante'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['name' => $role['name']], $role);
        }
    }
}