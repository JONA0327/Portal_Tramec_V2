<?php

use App\Http\Controllers\ExistenciasController;
use App\Http\Controllers\PriceRulesController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StockWebhookController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\WarehousesController;
use Illuminate\Support\Facades\Route;

// La raíz del sitio siempre manda al login (no hay landing pública en este portal).
Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Descuentos y aumentos de precio (solo al mostrar, ver PriceAdjuster). Solo
// ADMINISTRADOR (users.level 0), igual que la administración de usuarios en v1.
Route::middleware(['auth', 'verified', 'can:admin'])->prefix('precios')->name('price-rules.')->group(function () {
    Route::get('/', [PriceRulesController::class, 'index'])->name('index');
    Route::get('/nueva', [PriceRulesController::class, 'create'])->name('create');
    Route::get('/vista-previa', [PriceRulesController::class, 'preview'])->name('preview');
    Route::post('/', [PriceRulesController::class, 'store'])->name('store');
    Route::get('/{priceRule}/editar', [PriceRulesController::class, 'edit'])->name('edit');
    Route::put('/{priceRule}', [PriceRulesController::class, 'update'])->name('update');
    Route::patch('/{priceRule}/activar', [PriceRulesController::class, 'toggle'])->name('toggle');
    Route::delete('/{priceRule}', [PriceRulesController::class, 'destroy'])->name('destroy');
});

// Administración de usuarios (solo ADMINISTRADOR): alta, edición, activar/desactivar y nivel.
Route::middleware(['auth', 'verified', 'can:admin'])->prefix('usuarios')->name('users.')->group(function () {
    Route::get('/', [UsersController::class, 'index'])->name('index');
    Route::get('/nuevo', [UsersController::class, 'create'])->name('create');
    Route::post('/', [UsersController::class, 'store'])->name('store');
    Route::get('/{user}/editar', [UsersController::class, 'edit'])->name('edit');
    Route::put('/{user}', [UsersController::class, 'update'])->name('update');
    Route::patch('/{user}', [UsersController::class, 'quick'])->name('quick');
});

// Sondeo del banner "servicio de existencias caído" (ver x-stock-status).
Route::get('/stock/estado', function () {
    \App\Support\StockHealth::check();
    $since = \App\Support\StockHealth::downSince();

    return response()->json([
        'down' => (bool) $since,
        'since' => $since ? \Illuminate\Support\Carbon::parse($since)->locale('es')->diffForHumans() : null,
    ]);
})->middleware(['auth', 'verified'])->name('stock.status');

// Catálogo servido desde la caché del microservicio de existencias (ver StockService).
// v1 además filtraba por marca/clasificación y traía import/export; eso todavía no
// se porta acá.
Route::get('/productos', [ProductsController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('products');

// Precios por canal, fusionado dentro del mismo catálogo de arriba: cada fila
// del ERP se cruza con la tabla local `products` por idERP/idERP_empresa (ver
// ProductErpMatcher). El nombre/código nunca se edita acá: lo mantiene al día
// el ERP (ver App\Listeners\SyncLocalProductFromErp).
Route::middleware(['auth', 'verified'])->prefix('productos')->name('products.')->group(function () {
    Route::post('/registrar', [ProductsController::class, 'register'])->name('register');
    Route::post('/bloquear', [ProductsController::class, 'block'])->name('block');
    Route::get('/{product}', [ProductsController::class, 'show'])->name('show');
    Route::get('/{product}/editar', [ProductsController::class, 'edit'])->name('edit');
    Route::put('/{product}', [ProductsController::class, 'update'])->name('update');
    Route::get('/{product}/eliminar', [ProductsController::class, 'confirmDestroy'])->name('confirm-destroy');
    Route::delete('/{product}', [ProductsController::class, 'destroy'])->name('destroy');
});

// Almacenes: se empareja con el ERP en vivo en cada visita a index() (ver
// WarehouseErpMatcher), son muy pocas filas y así un nombre editado en
// CONTPAQi se refleja solo, sin scheduler ni botón.
Route::get('/almacenes', [WarehousesController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('warehouses');

Route::middleware(['auth', 'verified'])->prefix('almacenes')->name('warehouses.')->group(function () {
    Route::post('/registrar', [WarehousesController::class, 'register'])->name('register');
    Route::get('/{warehouse}/eliminar', [WarehousesController::class, 'confirmDestroy'])->name('confirm-destroy');
    Route::delete('/{warehouse}', [WarehousesController::class, 'destroy'])->name('destroy');
});

// Existencias: cantidad por producto+almacén, en vivo desde StockLevelService
// (caché + sync completo + webhook en tiempo real, ver servicio-stock::_vigilar_existencias).
// Se puede guardar un ajuste manual por encima (ver ExistenciaOverride), pero
// nunca se toca el ERP ni servicio-stock desde acá.
Route::get('/existencias', [ExistenciasController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('existencias');

// Importación de Excel: solo ADMINISTRADOR, en dos pasos (importar -> revisar -> confirmar).
Route::middleware(['auth', 'verified', 'can:admin'])->prefix('existencias/importar')->name('existencias.importar')->group(function () {
    Route::post('/', [ExistenciasController::class, 'importar']);
    Route::get('/revisar', [ExistenciasController::class, 'revisarImportacion'])->name('.revisar');
    Route::post('/confirmar', [ExistenciasController::class, 'confirmarImportacion'])->name('.confirmar');
    Route::post('/cancelar', [ExistenciasController::class, 'cancelarImportacion'])->name('.cancelar');
});

Route::middleware(['auth', 'verified'])->prefix('existencias')->name('existencias.')->group(function () {
    Route::get('/inconsistencias', [ExistenciasController::class, 'inconsistencias'])->name('inconsistencias');
    Route::post('/inconsistencias/{override}/mantener', [ExistenciasController::class, 'mantenerManual'])->name('inconsistencias.mantener');
    Route::post('/inconsistencias/{override}/aceptar-erp', [ExistenciasController::class, 'aceptarErp'])->name('inconsistencias.aceptar-erp');

    Route::get('/{empresa}/{idProducto}/{idAlmacen}/editar', [ExistenciasController::class, 'edit'])->name('edit');
    Route::put('/{empresa}/{idProducto}/{idAlmacen}', [ExistenciasController::class, 'update'])->name('update');
    Route::post('/{empresa}/{idProducto}/{idAlmacen}/ocultar', [ExistenciasController::class, 'ocultar'])->name('ocultar');
    Route::post('/{empresa}/{idProducto}/{idAlmacen}/restaurar', [ExistenciasController::class, 'restaurar'])->name('restaurar');
    Route::get('/{empresa}/{idProducto}/{idAlmacen}/dar-de-baja', [ExistenciasController::class, 'confirmBaja'])->name('confirm-baja');
    Route::post('/{empresa}/{idProducto}/{idAlmacen}/dar-de-baja', [ExistenciasController::class, 'darDeBaja'])->name('dar-de-baja');
});

// servicio-stock llama acá apenas detecta un cambio en el catálogo (ver
// main.py::_vigilar_catalogo). No lleva 'auth' (no es un usuario logueado quien
// llama) — se valida con un secreto compartido, ver StockWebhookController.
Route::post('/webhooks/stock-changed', [StockWebhookController::class, 'handle'])
    ->name('webhooks.stock-changed');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
