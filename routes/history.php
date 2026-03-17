<?php

use App\Http\Controllers\HistoryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::resource('history', HistoryController::class);
});

Route::get('/confirm', [HistoryController::class, 'confirm'])->name('confirm');