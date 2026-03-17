<?php

use App\Http\Controllers\InboundController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::resource('inbounds', InboundController::class)->except(['show']);

    Route::get('/inbounds/search', [InboundController::class, 'search'])->name('inbounds.search');

    Route::get('/inbounds/qrcode', [InboundController::class, 'qrcode'])->name('inbounds.qrcode');
});

