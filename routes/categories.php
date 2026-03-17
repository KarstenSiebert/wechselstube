<?php

use App\Http\Controllers\CategoryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {    
    Route::get('/categories/search', [CategoryController::class, 'search'])->name('categories.search');
});