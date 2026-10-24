<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class InvitationService
{
    public function resend(User $user): void
    {
        if (! $user->totp_confirmed_at) {
            $this->send($user);

            return;
        }
        $url = rtrim(config('app.frontend_url'), '/').'/login';
        Mail::send('emails.account-access', ['person' => $user, 'url' => $url], fn ($m) => $m->to($user->email, $user->name)->subject('Tu acceso al sistema de notas ULS'));
    }

    public function resetAccess(User $user): void
    {
        DB::transaction(function () use ($user) {
            $account = User::lockForUpdate()->findOrFail($user->id);
            abort_unless(in_array($account->role, ['teacher', 'student']), 422, 'El restablecimiento por QR es para docentes y estudiantes.');
            $this->send($account, true);
            $account->forceFill(['remember_token' => null, 'email_verified_at' => null])->save();
            DB::table('sessions')->where('user_id', $account->id)->delete();
            DB::table('password_reset_tokens')->where('email', $account->email)->delete();
        });
    }

    public function send(User $user, bool $recovery = false): void
    {
        $token = Str::random(64);
        $password = Str::password(16);
        $user->forceFill(['password' => $password, 'must_change_password' => true, 'totp_secret' => app(Totp::class)->secret(), 'totp_confirmed_at' => null, 'totp_last_counter' => null, 'invitation_token' => hash('sha256', $token), 'invitation_expires_at' => now()->addDays(3)])->save();
        $url = rtrim(config('app.frontend_url'), '/').($user->role === 'admin' ? '/login' : '/activar/'.$token);
        Mail::send($recovery ? 'emails.access-reset' : 'emails.invitation', ['person' => $user, 'temporaryPassword' => $password, 'url' => $url], fn ($m) => $m->to($user->email, $user->name)->subject($recovery ? 'Restablece tu acceso ULS · Configura tu nuevo QR' : '¡Bienvenido a ULS! Tu nueva etapa comienza aquí'));
    }
}
