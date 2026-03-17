<?php

use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::resource('payments', PaymentController::class)->except(['show']);
    
    Route::post('/payments/{payment}/pay', [PaymentController::class, 'pay'])->name('payments.pay');

    Route::post('/payments/{payment}/deny', [PaymentController::class, 'deny'])->name('payments.deny');
});