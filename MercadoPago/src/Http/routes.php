<?php

use Illuminate\Support\Facades\Route;
use Midnight\MercadoPago\Http\Controllers\StandardController;

Route::group(['middleware' => ['web']], function () {
    Route::prefix('mercadopago/standard')->group(function () {
        Route::get('/redirect', [StandardController::class, 'redirect'])->name('mercadopago.standard.redirect');

        Route::get('/success', [StandardController::class, 'success'])->name('mercadopago.standard.success');

        Route::get('/cancel', [StandardController::class, 'cancel'])->name('mercadopago.standard.cancel');

        Route::get('/pending', [StandardController::class, 'pending'])->name('mercadopago.standard.pending');
    });
});

Route::post('mercadopago/standard/ipn', [StandardController::class, 'ipn'])
    ->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class)
    ->name('mercadopago.standard.ipn');
