<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class MailStatus
{
    public function check(): array
    {
        try {
            $transport = Mail::mailer('smtp')->getSymfonyTransport();
            $transport->start();
            $transport->stop();

            return ['ok' => true, 'process_id' => getmypid(), 'message' => 'Conexión TLS y autenticación SMTP verificadas desde el servidor web. No se enviaron correos.'];
        } catch (TransportExceptionInterface $e) {
            return ['ok' => false, 'process_id' => getmypid(), 'message' => $this->message($e)];
        }
    }

    public function message(TransportExceptionInterface $error): string
    {
        $reason = strtolower($error->getMessage());
        if (str_contains($reason, 'forbidden by its access permissions') || str_contains($reason, 'permission denied')) {
            return 'El servidor tiene bloqueada la conexión de red a Mailjet. Inicia el backend desde una terminal con acceso de red o autoriza su ejecución fuera del entorno restringido. La operación no se completó.';
        }
        if (str_contains($reason, 'authenticate') || str_contains($reason, '535')) {
            return 'Mailjet rechazó la autenticación SMTP. Revisa la API key y la clave secreta del backend. La operación no se completó.';
        }

        return 'No se pudo enviar el correo mediante Mailjet. Revisa la conexión de red, la configuración SMTP y la validación del remitente. La operación no se completó.';
    }
}
