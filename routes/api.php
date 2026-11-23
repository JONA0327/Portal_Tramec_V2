<?php

use App\Http\Controllers\Api\ApiController;
use Illuminate\Support\Facades\Route;

// API para terceros (mismas rutas que v1). Se entra con POST /api/login (username = correo, password) o con un token
// creado por el admin (`php artisan api:token correo`); luego "Authorization: Bearer <token>".
Route::post('login', [ApiController::class, 'login'])->middleware('throttle:10,1');
Route::post('login_app_tmc', [ApiController::class, 'login'])->middleware('throttle:10,1');

Route::middleware(['api.token', 'throttle:120,1'])->group(function () {
    Route::get('products', [ApiController::class, 'products']);
    Route::get('product_by_id/{id}', [ApiController::class, 'productById']);
    Route::get('product_by_name/{name}', [ApiController::class, 'productByName']);
    Route::get('products_by_brand/{brand}', [ApiController::class, 'productsByBrand']);
    Route::get('product_list_price/{id}', [ApiController::class, 'productListPrice']);
    Route::get('stocks', [ApiController::class, 'stocks']);
    Route::get('stocks_by_warehouse/{id}', [ApiController::class, 'stocksByWarehouse']);
    Route::get('stocks_by_id/{id}', [ApiController::class, 'stocksById']);

    // Equipo TMC (no clientes): todos los campos y los 5 precios.
    Route::get('products_tmc', [ApiController::class, 'productsTmc']);
    Route::get('product_by_id_tmc/{id}', [ApiController::class, 'productByIdTmc']);
    Route::get('product_by_name_tmc/{name}', [ApiController::class, 'productByNameTmc']);
    Route::get('products_by_brand_tmc/{brand}', [ApiController::class, 'productsByBrandTmc']);
});
