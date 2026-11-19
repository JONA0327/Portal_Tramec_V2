{{-- Pantalla de mantenimiento: mismo diseño que el inicio de sesión (x-layouts.auth). --}}
<x-layouts.auth>
    <a href="/" class="ad-auth-logo">
        <h4 class="text-center">Portal de Existencias</h4>
    </a>
    <h2><span class="primary">Portal en mantenimiento</span></h2>
    <p>{{ config('app.mantenimiento_mensaje') }}</p>
    <p class="mb-0"><small>Gracias por tu paciencia. — TRAMEC</small></p>
</x-layouts.auth>
