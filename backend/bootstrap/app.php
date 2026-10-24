<?php

use App\Http\Middleware\AcademicAccess;
use App\Services\MailStatus;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias(['academic' => AcademicAccess::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (TransportExceptionInterface $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => app(MailStatus::class)->message($e)], 503);
            }
        });
    })->create();
