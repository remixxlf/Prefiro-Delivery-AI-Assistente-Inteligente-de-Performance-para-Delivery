<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Todas as rotas SPA apontam para o index.blade.php
| O roteamento real é feito pelo Vue Router no frontend.
*/

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');