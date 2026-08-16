<?php

namespace Database\Seeders;

use App\Services\GamificationService;
use Illuminate\Database\Seeder;

class GamificationSeeder extends Seeder
{
    public function run(): void
    {
        GamificationService::syncDefinitions();
    }
}