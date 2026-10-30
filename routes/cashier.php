<?php

use App\Http\Middleware\EnsureBillingAvailable;
use Illuminate\Support\Facades\Route;
use Laravel\Cashier\Http\Controllers\PaymentController;
use Laravel\Cashier\Http\Controllers\WebhookController;

// Cashier's own routes (ignored in AppServiceProvider), same URLs and names, no `web` group.
Route::prefix(config('cashier.path', 'stripe'))->name('cashier.')->middleware(EnsureBillingAvailable::class)->group(function (): void {
    Route::get('payment/{id}', [PaymentController::class, 'show'])->name('payment');
    Route::post('webhook', [WebhookController::class, 'handleWebhook'])->name('webhook');
});
