<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SmtpCheck extends Command
{
    protected $signature = 'uls:smtp-check';

    protected $description = 'Verifica conexión y autenticación SMTP sin enviar correos';

    public function handle(): int
    {
        try {
            $transport = Mail::mailer('smtp')->getSymfonyTransport();
            $transport->start();
            $transport->stop();
            $this->info('Conexión TLS y autenticación SMTP correctas. No se enviaron correos.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $reason = $e->getMessage();
            foreach (['username', 'password'] as $field) {
                $value = (string) config('mail.mailers.smtp.'.$field);
                if ($value !== '') $reason = str_replace([$value, base64_encode($value)], '[oculto]', $reason);
            }
            $this->line($reason);
            $this->error('No fue posible autenticar SMTP. Revisa conectividad, credenciales y configuración de Mailjet.');

            return self::FAILURE;
        }
    }
}
