<?php

use Illuminate\Support\Facades\Route;
use Midnight\MercadoPago\Http\Controllers\StandardController;

// Completely isolated IPN route - MUST be first, before any middleware groups
Route::post('mercadopago/standard/ipn', function(\Illuminate\Http\Request $request) {
    try {
        // Directly instantiate the helper to avoid any dependency injection issues
        $orderRepository = app(\Webkul\Sales\Repositories\OrderRepository::class);
        $invoiceRepository = app(\Webkul\Sales\Repositories\InvoiceRepository::class);
        $ipnHelper = new \Midnight\MercadoPago\Helpers\Ipn($orderRepository, $invoiceRepository);

        $ipnHelper->processIpn($request->all());

        return response()->json(['status' => 'OK'], 200, ['Content-Type' => 'application/json']);
    } catch (\Throwable $e) {
        // Log error but always return 200 to prevent MercadoPago retries
        \Illuminate\Support\Facades\Log::error('MercadoPago IPN Route Error: ' . $e->getMessage());
        return response()->json(['status' => 'ERROR', 'message' => 'Internal error'], 200, ['Content-Type' => 'application/json']);
    }
})->name('mercadopago.standard.ipn');

Route::group(['middleware' => ['web']], function () {
    Route::prefix('mercadopago/standard')->group(function () {
        Route::get('/redirect', [StandardController::class, 'redirect'])->name('mercadopago.standard.redirect');

        Route::get('/success', [StandardController::class, 'success'])->name('mercadopago.standard.success');

        Route::get('/cancel', [StandardController::class, 'cancel'])->name('mercadopago.standard.cancel');

        Route::get('/pending', [StandardController::class, 'pending'])->name('mercadopago.standard.pending');
    });
});
