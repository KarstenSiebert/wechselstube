<?php

use App\Http\Controllers\MintController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::resource('mints', MintController::class)->except(['show']);

    Route::post('/mints/append', [MintController::class, 'append'])->name('mints.append');

    Route::post('/mints/mint', [MintController::class, 'mint'])->name('mints.mint');

    Route::post('/mints/burn', [MintController::class, 'burn'])->name('mints.burn');

    Route::get('/mints/search', [MintController::class, 'search'])->name('mint.search');
});