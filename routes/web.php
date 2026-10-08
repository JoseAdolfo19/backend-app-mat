<?php

use Illuminate\Support\Facades\Route;

// Rutas web públicas; la página inicial muestra la vista de bienvenida.
Route::get('/', function () {
    return view('welcome');
});
