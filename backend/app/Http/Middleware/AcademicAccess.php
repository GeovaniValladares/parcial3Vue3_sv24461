<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AcademicAccess
{
    public function handle(Request $request, Closure $next, string $role = 'any')
    {
        abort_unless($request->user(), 401, 'Inicia sesión para continuar.');
        if ($role !== 'password') {
            abort_if($request->user()->must_change_password, 403, 'Debes cambiar tu contraseña antes de continuar.');
        }
        if ($role === 'admin') {
            abort_unless($request->user()->role === 'admin', 403, 'Acceso exclusivo para administradores.');
        }

        if ($role === 'staff') {
            abort_unless(in_array($request->user()->role, ['admin', 'teacher']), 403, 'Los estudiantes solo pueden consultar sus propias notas.');
        }

        return $next($request);
    }
}
