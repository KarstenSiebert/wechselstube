<?php

namespace App\Http\Controllers\Auth;

use App\Models\Wallet;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('assets', absolute: false).'?verified=1');
        }

        $request->fulfill();

        // The user wallet gets activated after email verification.

        Wallet::where('user_id', $request->user()->id)->update(['is_active' => true]);            

        return redirect()->intended(route('assets', absolute: false).'?verified=1');
    }
}
