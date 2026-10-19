<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // servicio-stock (Python) llama a este webhook sin token CSRF; se autentica
        // con un secreto compartido en el header X-Webhook-Secret (ver
        // StockWebhookController), no con la sesión del navegador.
        $middleware->alias(['nivel' => \App\Http\Middleware\EnsureLevelCanAccess::class]);
        $middleware->web(append: [\App\Http\Middleware\EnsureUserIsActive::class]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/stock-changed',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
