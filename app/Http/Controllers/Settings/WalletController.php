<?php

namespace App\Http\Controllers\Settings;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Wallet;
use Inertia\Inertia;
use Inertia\Response;
use Auth;

class WalletController extends Controller
{
    /**
     * Show the user's wallet address.
     */
    public function show(): Response
    {
        $user = Auth::user();

        $qrcode = $this->generateQrCode($user->wallet->address);

        $keys = $this->getKeys($user->wallet);

        return Inertia::render('settings/Wallet', [
            'address' => $user->wallet->address,
            'qrcode'  => $qrcode,
            'keys'    => $keys
        ]);
    }

    private function generateQrCode($address): string
    {
         $qrCode = "data:image/png;base64,".base64_encode(QrCode::size(234)
                    ->format('png')
                    ->color(148, 164, 163)
                    ->errorCorrection('H')
                    ->margin(1)
                    ->encoding('UTF-8')
                    ->generate($address));                    
        
        return $qrCode;
    }

    private function getKeys(Wallet $wallet): string
    {
        $keys  = '';
        $keys .= $wallet->verification_key.PHP_EOL;
        $keys .= $wallet->signing_key;
                
        return $str = preg_replace('/\\\"/',"\"", json_encode($keys, JSON_UNESCAPED_SLASHES));
    }

}
