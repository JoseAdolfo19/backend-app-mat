<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DniSeeder extends Seeder
{
    public function run(): void
    {
        $students = DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->where('roles.name', 'student')
            ->select('users.id')
            ->get();

        $dni = 70000001;

        foreach ($students as $student) {
            DB::table('users')
                ->where('id', $student->id)
                ->update(['dni' => (string) $dni]);
            $dni++;
        }
    }
}
