<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoAdminAccount
{
    public const EMAIL = 'admin.prueba@uls.edu.sv';
    public const PASSWORD = 'PruebaULS@2026!';

    public function ensure(bool $reset = false): User
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('La cuenta de evaluación solo está disponible en el entorno local.');
        }

        return DB::transaction(function () use ($reset) {
            $user = User::where('email', self::EMAIL)->lockForUpdate()->first();
            if ($user && $user->role !== 'admin') {
                throw new RuntimeException('El correo de prueba ya pertenece a otro usuario. No se modificó esa cuenta.');
            }
            if ($user && ! $reset) return $user;

            $values = [
                'name' => 'Administrador de evaluación',
                'email' => self::EMAIL,
                'role' => 'admin',
                'password' => self::PASSWORD,
                'must_change_password' => false,
                'totp_secret' => null,
                'totp_confirmed_at' => null,
                'totp_last_counter' => null,
                'invitation_token' => null,
                'invitation_expires_at' => null,
                'remember_token' => null,
            ];
            if ($user) {
                $user->forceFill($values)->save();
                DB::table('sessions')->where('user_id', $user->id)->delete();
                DB::table('password_reset_tokens')->where('email', self::EMAIL)->delete();
            } else {
                $user = User::create($values);
            }

            return $user;
        });
    }
}
