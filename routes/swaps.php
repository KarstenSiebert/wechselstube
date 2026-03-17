<?php

use App\Http\Controllers\SwapController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::resource('swaps', SwapController::class)->except(['show']);

    Route::get('/swaps/search', [SwapController::class, 'search'])->name('swaps.search');
});