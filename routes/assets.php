<?php

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\AssetController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('assets', [AssetController::class, 'index'])->name('assets');

    Route::get('assets/submit', [AssetController::class, 'index'])->name('assets.submit.create');

    Route::post('assets/submit', [AssetController::class, 'store'])->name('assets.submit');

    Route::post('assets/transfer', [AssetController::class, 'transfer'])->name('assets.transfer');

    Route::post('assets/payment', [AssetController::class, 'payment'])->name('assets.payment');

    Route::post('assets/confirm', [AssetController::class, 'confirm'])->name('assets.confirm');

    Route::get('/users/search', function(Request $request) {
        $query = $request->input('q', '');
        
        $currentUserId = auth()->id();

        $users = User::with('wallet')
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->where('id', '!=', $currentUserId)
            ->where('email_verified_at', '<>', null)
            ->take(10)
            ->get();

        $result = $users->map(function($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'wallet_address' => $user->wallet?->address ?? ''
            ];
        });

        return response()->json($result);
    });

});