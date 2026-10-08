<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

/**
 * Registra los roles de usuario admitidos por la plataforma.
 */
class RolesSeeder extends Seeder
{
    /**
     * Inserta o actualiza los roles del sistema sin duplicarlos por nombre.
     */
    public function run(): void
    {
        $roles = [
            ['name' => Role::ADMIN, 'description' => 'Administrador del sistema'],
            ['name' => Role::TEACHER, 'description' => 'Docente'],
            ['name' => Role::STUDENT, 'description' => 'Estudiante'],
            ['name' => Role::PARENT, 'description' => 'Padre de familia'],
            ['name' => Role::DIRECTOR, 'description' => 'Director académico'],
            ['name' => Role::COORDINATOR, 'description' => 'Coordinador académico'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['name' => $role['name']], $role);
        }
    }
}