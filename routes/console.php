<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Mantiene la caché de productos del microservicio de existencias al día sin que las
// páginas del portal tengan que llamar a la API externa en cada visita. El comando
// decide solo si conviene un sync incremental o completo (ver StockService::sync()).
Schedule::command('stock:sync')->everyFifteenMinutes()->withoutOverlapping();

// Reconcilia los productos locales (precios por canal) contra el catálogo del ERP
// recién sincronizado arriba. Corre después para trabajar siempre con datos frescos.
Schedule::command('products:match-erp')->everyFifteenMinutes()->withoutOverlapping();

// Red de seguridad del webhook de existencias (servicio-stock::_vigilar_existencias):
// si el webhook falla (tunnel caído, portal reiniciado, etc.) esto igual refresca
// la caché completa cada 15 min, así ningún cambio se pierde para siempre.
Schedule::command('stock-levels:sync')->everyFifteenMinutes()->withoutOverlapping();
