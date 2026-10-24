<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Totp;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class LocalActivation extends Command
{
    protected $signature = 'uls:activacion-local {email}';

    protected $description = 'Renueva el acceso inicial de una cuenta sin activar, solo en entorno local';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Disponible solo en entorno local.');

            return self::FAILURE;
        }
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user || $user->totp_confirmed_at) {
            $this->error('La cuenta no existe o ya está activa.');

            return self::FAILURE;
        }
        $token = Str::random(64);
        $password = Str::password(16);
        $user->forceFill(['password' => $password, 'totp_secret' => app(Totp::class)->secret(), 'invitation_token' => hash('sha256', $token), 'invitation_expires_at' => now()->addDays(3), 'must_change_password' => true])->save();
        $folder = base_path('../.runtime');
        if (! is_dir($folder)) {
            mkdir($folder, 0700, true);
        }file_put_contents($folder.'/acceso-inicial.txt', "Usuario: {$user->email}\nClave temporal: $password\nActivación: ".rtrim(config('app.frontend_url'), '/')."/activar/$token\n");
        $this->info('Acceso inicial guardado en .runtime/acceso-inicial.txt.');

        return self::SUCCESS;
    }
}
