<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateAdmin extends Command
{
    protected $signature = 'uls:admin {email} {--name=Administrador ULS}';

    protected $description = 'Crea un administrador y envía su invitación por correo';

    public function handle(InvitationService $service): int
    {
        $email = strtolower($this->argument('email'));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Correo inválido.');

            return self::FAILURE;
        }
        if (User::where('email', $email)->exists()) {
            $this->error('Este usuario ya existe.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($email, $service) {
            $user = User::create(['name' => $this->option('name'), 'email' => $email, 'role' => 'admin', 'password' => Str::password(24)]);
            $service->send($user);
        });
        $this->info('Administrador creado. Revisa su correo para activar Google Authenticator.');

        return self::SUCCESS;
    }
}
