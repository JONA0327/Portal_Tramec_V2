<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Manuales y catálogos: enlaces a los mismos PDF de tramec.mx/descargas (config/manuales.php).
 * No se alojan archivos en el portal.
 */
class ManualesController extends Controller
{
    public function index(): View
    {
        return view('manuales', config('manuales'));
    }
}
