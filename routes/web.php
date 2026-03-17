<?php

use App\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Inertia\Inertia;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('assets');
    }
    // return Inertia::render('Welcome');

    return redirect()->route('login');
})->name('home');

Route::get('dashboard', function () {
    if (Auth::check()) {
        return redirect()->route('assets'); 
    }
    // return Inertia::render('Welcome');

    return redirect()->route('login');
})->name('dashboard');

Route::post('/language', function (Request $request) {
    $locale = strtolower(substr($request->input('locale', 'en'), 0, 2));
    
    $supported = ['en', 'de'];
    
    if (!in_array($locale, $supported)) {
        $locale = 'en';
    }
    
    $request->session()->put('locale', $locale);

    return redirect()->back();
});


require __DIR__.'/settings.php';
require __DIR__.'/payments.php';
require __DIR__.'/inbounds.php';
require __DIR__.'/contacts.php';
require __DIR__.'/categories.php';
require __DIR__.'/babelfees.php';
require __DIR__.'/history.php';
require __DIR__.'/assets.php';
require __DIR__.'/mints.php';
require __DIR__.'/swaps.php';
require __DIR__.'/auth.php';
