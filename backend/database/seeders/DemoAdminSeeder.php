<?php

namespace Database\Seeders;

use App\Services\DemoAdminAccount;
use Illuminate\Database\Seeder;

class DemoAdminSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('La cuenta de evaluación no se crea fuera del entorno local.');
            return;
        }
        app(DemoAdminAccount::class)->ensure();
        $this->command?->info('Administrador de evaluación disponible: '.DemoAdminAccount::EMAIL.' (sin OTP).');
    }
}
