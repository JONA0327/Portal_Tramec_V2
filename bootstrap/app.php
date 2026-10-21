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
        // El portal corre detrás de un túnel de Cloudflare (cloudflared -> localhost): se confía en sus
        // cabeceras X-Forwarded-* para que Laravel sepa que el sitio es https (cookie de sesión, URLs).
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'nivel' => \App\Http\Middleware\EnsureLevelCanAccess::class,
            'nostore' => \App\Http\Middleware\NoStore::class,
        ]);
        $middleware->web(append: [\App\Http\Middleware\EnsureUserIsActive::class]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/stock-changed',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 419 (token CSRF vencido o sesión perdida): en vez de la página de error, se vuelve al
        // formulario con un mensaje para reintentar -- en el login es lo que ve el usuario.
        // (Laravel convierte TokenMismatchException en un HttpException 419 antes de llegar aquí.)
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson()) {
                return null;
            }

            $mensaje = 'La página expiró. Intentá de nuevo.';

            return $request->user()
                ? back()->withInput($request->except('password', 'password_confirmation', 'current_password'))->with('error', $mensaje)
                : redirect()->route('login')->withInput($request->except('password', 'password_confirmation'))->withErrors(['email' => $mensaje]);
        });
    })->create();
