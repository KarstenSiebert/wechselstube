<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\EnsureApiAuthenticated;
use App\Http\Controllers\Api\ApiTransactionController;

Route::middleware(['throttle:api'])->group(function () {

    Route::post('/login', [ApiTransactionController::class, 'login'])->name('api.login');

    Route::post('/check', [ApiTransactionController::class, 'check'])->name('api.check');

    Route::post('/create', [ApiTransactionController::class, 'create'])->name('api.create');

    Route::post('/delete', [ApiTransactionController::class, 'delete'])->name('api.delete');

    Route::get('/service', [ApiTransactionController::class, 'search'])->name('api.search');

    Route::post('/service', [ApiTransactionController::class, 'settle'])->name('api.settle');    
});

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

    Route::post('/confirm', [ApiTransactionController::class, 'confirm'])->name('api.confirm');

    Route::post('/logout', [ApiTransactionController::class, 'logout'])->name('api.logout');
});

