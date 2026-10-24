<?php

namespace App\Console\Commands;

use App\Services\DemoAdminAccount;
use Illuminate\Console\Command;
use RuntimeException;

class DemoAdmin extends Command
{
    protected $signature = 'uls:demo-admin {--reset : Restablece únicamente las credenciales públicas del administrador de evaluación}';
    protected $description = 'Crea el administrador de prueba local, sin enviar correos ni pedir OTP';

    public function handle(DemoAdminAccount $account): int
    {
        try {
            $account->ensure((bool) $this->option('reset'));
        } catch (RuntimeException $error) {
            $this->error($error->getMessage());
            return self::FAILURE;
        }
        $this->info('Usuario: '.DemoAdminAccount::EMAIL);
        $this->info('Contraseña de evaluación: '.DemoAdminAccount::PASSWORD);
        $this->info('Ingresa en http://127.0.0.1:5173/login. No requiere QR, OTP ni cambio inicial de clave.');
        if (! $this->option('reset')) $this->line('Si esta cuenta ya existía, se conservaron sus credenciales. Usa --reset para restaurarlas.');
        return self::SUCCESS;
    }
}
