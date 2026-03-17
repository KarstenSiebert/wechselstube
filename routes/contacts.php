<?php

use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::resource('contacts', ContactController::class)->except(['show']);

    Route::get('/contacts/search', [ContactController::class, 'search'])->name('contacts.search');
});