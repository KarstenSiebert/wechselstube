<?php

use App\Http\Controllers\BabelFeeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::resource('babelfees', BabelFeeController::class)->except(['show']);

    Route::get('/babelfees/search', [BabelFeeController::class, 'search'])->name('babelfees.search');
});