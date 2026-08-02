<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            AdminUserSeeder::class,
            TestDataSeeder::class,
            MathContentSeeder::class,
            DniSeeder::class,
            ExamSeeder::class,
            RankingSeeder::class,
        ]);
    }
}