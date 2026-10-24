<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\User;
use App\Services\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private function invitation(string $token): User
    {
        $u = User::where('invitation_token', hash('sha256', $token))->where('invitation_expires_at', '>', now())->whereNull('totp_confirmed_at')->first();
        abort_unless($u, 404, 'La invitación no es válida, ya fue utilizada o venció. Solicita una nueva al administrador.');

        return $u;
    }

    public function invitationInfo(string $token, Totp $totp)
    {
        $u = $this->invitation($token);

        return response()->json(['name' => $u->name, 'secret' => $u->totp_secret, 'uri' => $totp->uri($u->totp_secret, $u->email)])->header('Cache-Control', 'no-store');
    }

    public function confirm(Request $r, string $token, Totp $totp)
    {
        $r->validate(['code' => 'required|digits:6']);
        $user = DB::transaction(function () use ($r, $token, $totp) {
            $u = $this->invitation($token);
            $u = User::lockForUpdate()->findOrFail($u->id);
            abort_if($u->totp_confirmed_at, 410, 'Esta invitación ya fue utilizada.');
            $counter = $totp->verify($u->totp_secret, $r->code);
            if ($counter === null) {
                throw ValidationException::withMessages(['code' => 'Código incorrecto o vencido. Revisa la hora de tu teléfono.']);
            }$u->forceFill(['totp_confirmed_at' => now(), 'totp_last_counter' => $counter, 'invitation_token' => null, 'invitation_expires_at' => null, 'email_verified_at' => now()])->save();

            return $u;
        });

        return $this->startSession($r, $user);
    }

    private function startSession(Request $request, User $user): array
    {
        $request->session()->forget(['challenge_user', 'challenge_expires', 'challenge_attempts', 'challenge_fingerprint']);
        $user->forceFill(['last_login_at' => now()])->save();
        Auth::login($user);
        $request->session()->regenerate();

        return ['user' => $user->fresh()];
    }

    public function login(Request $r)
    {
        $data = $r->validate(['email' => 'required|email', 'password' => 'required|string']);
        $r->session()->forget(['challenge_user', 'challenge_expires', 'challenge_attempts', 'challenge_fingerprint']);
        $u = User::where('email', strtolower($data['email']))->first();
        if (! $u || ! Hash::check($data['password'], $u->password)) {
            throw ValidationException::withMessages(['email' => 'El correo o la contraseña son incorrectos.']);
        }
        Auth::logout();
        if ($u->role === 'admin') {
            return $this->startSession($r, $u);
        }
        if (! $u->totp_confirmed_at) {
            throw ValidationException::withMessages(['email' => 'Activa tu cuenta con el enlace que recibiste por correo.']);
        }$r->session()->regenerate();
        $r->session()->put(['challenge_user' => $u->id, 'challenge_expires' => time() + 300, 'challenge_attempts' => 0, 'challenge_fingerprint' => hash('sha256', $u->password.'|'.$u->totp_secret)]);

        return ['message' => 'Ingresa tu código de Google Authenticator.'];
    }

    public function verify(Request $r, Totp $totp)
    {
        $r->validate(['code' => 'required|digits:6']);
        abort_unless($r->session()->get('challenge_user') && $r->session()->get('challenge_expires', 0) >= time(), 401, 'Tu intento de acceso venció. Inicia sesión de nuevo.');
        $attempts = $r->session()->increment('challenge_attempts');
        if ($attempts > 5) {
            $r->session()->forget('challenge_user');
            abort(429, 'Demasiados intentos. Inicia sesión de nuevo.');
        }$u = DB::transaction(function () use ($r, $totp) {
            $u = User::lockForUpdate()->findOrFail($r->session()->get('challenge_user'));
            abort_unless(hash_equals(hash('sha256', $u->password.'|'.$u->totp_secret), (string) $r->session()->get('challenge_fingerprint')), 401, 'Tu acceso fue restablecido. Inicia sesión de nuevo después de configurar el QR recibido por correo.');
            $counter = $totp->verify($u->totp_secret, $r->code, $u->totp_last_counter);
            if ($counter === null) {
                throw ValidationException::withMessages(['code' => 'Código incorrecto o utilizado. Espera el siguiente código de tu app.']);
            }$u->forceFill(['totp_last_counter' => $counter, 'last_login_at' => now()])->save();

            return $u;
        });
        $r->session()->forget(['challenge_user', 'challenge_expires', 'challenge_attempts', 'challenge_fingerprint']);
        Auth::login($u);
        $r->session()->regenerate();

        return ['user' => $u];
    }

    public function me(Request $r)
    {
        abort_unless($r->user(), 401);

        return ['user' => $r->user()];
    }

    public function profile(Request $request)
    {
        return ['user' => $request->user(), 'student' => Student::where('user_id', $request->user()->id)->first()];
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:150', 'phone' => 'nullable|string|max:25']);
        DB::transaction(function () use ($request, $data) {
            $request->user()->update($data);
            Student::where('user_id', $request->user()->id)->update(['name' => $data['name']]);
        });

        return ['user' => $request->user()->fresh(), 'message' => 'Tu perfil se actualizó correctamente.'];
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return ['message' => 'Sesión cerrada.'];
    }

    private function passwordRules(): array
    {
        return ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()->symbols()];
    }

    public function changePassword(Request $r)
    {
        $data = $r->validate(['current_password' => 'required|string', 'password' => $this->passwordRules()]);
        if (! Hash::check($data['current_password'], $r->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'La contraseña temporal no es correcta.']);
        }if (Hash::check($data['password'], $r->user()->password)) {
            throw ValidationException::withMessages(['password' => 'La nueva contraseña debe ser diferente.']);
        }$r->user()->forceFill(['password' => $data['password'], 'must_change_password' => false, 'remember_token' => null])->save();
        DB::table('sessions')->where('user_id', $r->user()->id)->where('id', '!=', $r->session()->getId())->delete();
        $r->session()->regenerate();

        return ['user' => $r->user()->fresh()];
    }

    public function forgot(Request $r)
    {
        $r->validate(['email' => 'required|email']);
        $u = User::where('email', $r->email)->first();
        if ($u) {
            $token = Password::broker()->createToken($u);
            $url = rtrim(config('app.frontend_url'), '/').'/restablecer/'.$token.'?email='.urlencode($u->email);
            Mail::send('emails.reset', ['url' => $url], fn ($m) => $m->to($u->email)->subject('Recupera tu acceso ULS'));
        }

        return ['message' => 'Si existe una cuenta, recibirás un correo.'];
    }

    public function reset(Request $r)
    {
        $data = $r->validate(['email' => 'required|email', 'token' => 'required|string', 'password' => $this->passwordRules()]);
        $status = Password::reset($data, function (User $u, string $password) {
            $u->forceFill(['password' => $password, 'remember_token' => null])->save();
            DB::table('sessions')->where('user_id', $u->id)->delete();
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'El enlace no es válido o ha vencido. Solicita uno nuevo.']);
        }

        return ['message' => 'Contraseña actualizada.'];
    }
}
