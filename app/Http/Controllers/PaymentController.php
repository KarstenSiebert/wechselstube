<?php

namespace App\Http\Controllers;

use Auth;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Contact;
use App\Models\Payment;
use App\Models\Transaction;
use App\Rules\TokenPolicy;
use Illuminate\Http\Request;
use App\Helpers\CardanoFingerprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; 


class PaymentController extends Controller
{
    use AuthorizesRequests;

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {   
        return Inertia::render('payments/Create', [        
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->merge([
            'quantity' => str_replace(',', '.', $request->quantity),
        ]);

        // dd($request->all());

        $validated = $request->validate([
            'id'         => ['required', 'exists:users,id'],
            'name'       => ['required', 'string', 'max:255', 'exists:users,name'],
            'address'    => ['required', 'string'],
            'asset_name' => ['required', 'string',  'max:32'],
            'policy_id'  => ['required', new TokenPolicy()],
            'decimals'   => ['required', 'in:0,1,2,3,4,5,6'], 
            'quantity'   => ['required', 'numeric', 'min:0.000001', 'regex:/^\d+(\.\d{1,6})?$/']
        ]);

        $assetHex = bin2hex($validated['asset_name']);
        $is_CIP68 = false;
        
        if ($validated['asset_name'] === 'USDM' || $validated['asset_name'] === 'Wechselstuben') {
            $assetHex = '0014df10'.$assetHex;    
            $is_CIP68 = true;
        }
        
        $fingerprint = CardanoFingerprint::fromPolicyAndName($validated['policy_id'], $validated['asset_name'], $is_CIP68);
        
        $error   = 'error';
        $message = 'validation.payment_request_not_created';

        if ($payment = Payment::create([
            'user_id'     => Auth::id(),
            'remote_id'   => $validated['id'],
            'asset_hex'   => $assetHex,
            'policy_id'   => $validated['policy_id'],
            'fingerprint' => $fingerprint,
            'quantity'    => $validated['quantity'],
            'decimals'    => $validated['decimals']
        ])) {
            $error   = 'success';
            $message = 'validation.payment_request_created_successfully';        
        }

        $cacheKey = 'payments_' . Auth::id();

        Cache::forget($cacheKey);

        return redirect()->route('payments.index')->with($error, __($message));
    }
 
    /**
     * Display the specified resource.
     */
    public function show(Payment $payment)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Payment $payment)
    {
        $this->authorize('update', $payment);

        $remote = User::where('id', $payment->remote_id)->firstOrfail();

        // dd($payment);

        if (str_starts_with($payment->asset_hex, '0014df10')) {
            $payment->asset_name = hex2bin(substr($payment->asset_hex, 8));

        } else {
            $payment->asset_name = hex2bin($payment->asset_hex);
        }

        // $payment->quantity = bcdiv($payment->quantity, bcpow("10", (string) $payment->decimals), $payment->decimals);

        $editPayment = ['id' => $payment->id, 
                        'name' => $remote->name, 
                        'address' => $remote->wallet->address, 
                        'asset_name' => $payment->asset_name, 
                        'policy_id' => $payment->policy_id,
                        'fingerprint' => $payment->fingerprint, 
                        'quantity' => $payment->quantity,
                        'decimals' => $payment->decimals,
                        'updated_at' => date($payment->updated_at)];

        // dd($editPayment);
        
        return Inertia::render('payments/Edit', [
            'payment' => $editPayment
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Payment $payment)
    {
        // dd($request->all());

        $this->authorize('update', $payment);

        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0']
        ]);

        $error   = 'error';
        $message = 'validation.payment_request_not_updated';

        if (Payment::where('id', $payment->id)->where('user_id', auth()->id())->update($validated)) {
            $error   = 'success';
            $message = 'validation.payment_request_updated_successfully';

            $cacheKey = 'payments_' . $payment->user_id;

            Cache::forget($cacheKey);
        }
        
        return redirect('payments')->with($error, __($message));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Payment $payment)
    {     
        $this->authorize('delete', $payment);

        $error   = 'error';
        $message = 'validation.payment_request_not_deleted';

        if (Payment::where('id', $payment->id)->where('user_id', auth()->id())->delete()) {
            $error   = 'success';
            $message = 'validation.payment_request_deleted_successfully';

            $cacheKey = 'payments_' . $payment->user_id;

            Cache::forget($cacheKey);
        }

        return back()->with($error, __($message));
    }

    public function pay(Payment $payment, Request $request)
    {
        $this->authorize('pay', $payment);

        $payment->update(['status' => 'paid']);

        $cacheKey = 'payments_' . $payment->user_id;

        Cache::forget($cacheKey);

        return back()->with('success', __('payment_has_been_paid'));
    }

    public function deny(Payment $payment, Request $request)
    {         
        $this->authorize('deny', $payment);

        $payment->update(['status' => 'denied']);

        return back()->with('error', __('payment_has_been_denied'));
    }

    public function index(Request $request)
    {
        $search = $request->input('search', null);
    
        $page = (int) $request->input('page', 1);
    
        $perPage = 10;

        $user = auth()->user();
        
        /*
        $cacheKey = 'payments_' . $user->id;

        $allPayments = Cache::remember($cacheKey, 600, function () use ($user) {
            return Payment::with(['user', 'remote'])->where('user_id', $user->id)->orWhere('remote_id', $user->id)->orderBy('updated_at', 'DESC')->limit(100)->get();
        });
        */

        $allPayments = Payment::with(['user', 'remote'])->where('user_id', $user->id)->orWhere('remote_id', $user->id)->orderBy('updated_at', 'DESC')->limit(100)->get();
        
        // dd($allPayments);

        if (!empty($search)) {   
            $searchTerms = preg_split('/\+/', strtolower(trim($search)));

            $filteredPayments = $allPayments->filter(function($payment) use ($searchTerms) {

                if (str_starts_with($payment->asset_hex, '0014df10') || str_starts_with($payment->asset_hex, '000643b0')) {
                    $paymentName = strtolower(hex2bin(substr($payment->asset_hex, 8)));
                
                } else {
                    $paymentName = strtolower(hex2bin($payment->asset_hex));
                }

                $name        = strtolower($payment->remote_user_name ?? '');
                $status      = strtolower($payment->status ?? '');
                $direction   = strtolower($payment->direction ?? '');                
                $fingerprint = strtolower($payment->fingerprint ?? '');

                return collect($searchTerms)->contains(fn($term) =>
                    str_contains($paymentName, $term) || str_contains($fingerprint, $term) || str_contains($name, $term) || str_contains($direction, $term) || str_contains($status, $term)
                );
            
            });
        
        } else {
            $filteredPayments = $allPayments;
        }

        $paymentList = $filteredPayments->map(function($payment) use ($user) {

            if (!$payment->quantity) return null;

            $payment->decimals = 0;
            
            if (!empty($payment->policy_id) && !empty($payment->asset_hex)) {                 
                $nameHex = '';

                if (str_starts_with($payment->asset_hex, '0014df10') || str_starts_with($payment->asset_hex, '000643b0')) {
                    $nameHex = substr($payment->asset_hex, 8);
                
                } else {
                    $nameHex = $payment->asset_hex;
                }

                $policyId = $payment->policy_id;

                $cacheKey = 'jsonMeta_' . $nameHex.$policyId;

                $jsonMeta = Cache::remember($cacheKey, 3600, function () use ($nameHex, $policyId) {
                    return CardanoFingerprint::getTokenJson($nameHex, $policyId);
                });  
                                        
                if (!empty($jsonMeta)) {
                    $payment->decimals = !empty($jsonMeta['decimals']) ? $jsonMeta['decimals'] : 0;
                }
            }   

            if (strtoupper($payment->asset_name) !== 'ADA') {                
                $is_CIP68 = false;
                    
                if (str_starts_with($payment->asset_hex, '0014df10')) {
                    $payment->asset_name = hex2bin(substr($payment->asset_hex, 8));
                    $is_CIP68 = true;

                } else {
                    $payment->asset_name = hex2bin($payment->asset_hex);
                }

                $predefined = [
                    'USDM'          => ['policy' => 'c48cbb3d5e57ed56e276bc45f99ab39abe94e6cd7ac39fb402da47ad', 'logo' => 'https://www.wechselstuben.net/storage/logos/usdm-coin.png', 'decimals' => 6],
                    'USDA'          => ['policy' => 'fe7c786ab321f41c654ef6c1af7b3250a613c24e4213e0425a7ae456', 'logo' => 'https://www.wechselstuben.net/storage/logos/usda-coin.png', 'decimals' => 6],
                    'DjedMicroUSD'  => ['policy' => '8db269c3ec630e06ae29f74bc39edd1f87c819f1056206e879a1cd61', 'logo' => 'https://www.wechselstuben.net/storage/logos/djed-coin.png', 'decimals' => 6],
                    'Wechselstuben' => ['policy' => '8dfd68762a95e06f3d66c04f2a688241767e8ea934a4144b4915a681', 'logo' => 'https://www.wechselstuben.net/storage/logos/wechselstuben-logo.png', 'decimals' => str_starts_with($payment->asset_hex, '0014df10') ? 6 : 0]
                ];
                                                
                if (isset($predefined[$payment->asset_name]) && $predefined[$payment->asset_name]['policy'] === $payment->policy_id) {
                        $img = $predefined[$payment->asset_name]['logo'];
                        $payment->decimals = $predefined[$payment->asset_name]['decimals'];

                } else {      
                    $assetHex = $payment->asset_hex;
                    $policyId = $payment->policy_id;

                    $cacheKey = 'img_' . $assetHex.$policyId;

                    $img = Cache::remember($cacheKey, 3600, function () use ($assetHex, $policyId) {                        
                        return CardanoFingerprint::getTokenLogo($assetHex, $policyId);
                    });                       
                }
            
                if (empty($img)) {
                    $img = 'https://www.wechselstuben.net/storage/logos/wechselstuben-logo.png';                    
                }                

            } else {                    
                $img = 'https://www.wechselstuben.net/storage/logos/cardano-ada-logo.png';

                $payment->decimals = 6;

                $payment->fingerprint = null;
            }

            $payment->logo_url = $img;

            $hash = Transaction::where('id', $payment->transaction_id)->where('is_confirmed', true)->value('transaction_id');

            $payment->transaction_id = !empty($hash) ? $hash : null;
                         
            return [
                'id' => $payment->id,
                'name' => $payment->remote_user_name,
                'address' =>$payment->remote_user_address,
                'policy_id' => $payment->policy_id,
                'hash' => $payment->transaction_id,
                'asset_name' => $payment->asset_name,
                'asset_hex' => $payment->asset_hex,
                'fingerprint' => $payment->fingerprint,
                'quantity' => $payment->quantity,
                'decimals' => $payment->decimals,
                'direction'  => $payment->direction,
                'logo_url' => $payment->logo_url,
                'status'   => $payment->status,
                'updated_at' => date($payment->updated_at),
                'canPay' => $user->can('pay', $payment),
                'canDeny' => $user->can('deny', $payment),
                'canUpdate' => $user->can('update', $payment),
                'canDelete' => $user->can('delete', $payment),
            ];

        })->values();
        
        if ($paymentList->count()) {

            $paginated = new LengthAwarePaginator(
                    $paymentList->forPage($page, $perPage)->values(),
                    $paymentList->count(),
                    $perPage,
                    $page,
                    ['path' => request()->url(), 'query' => $request->query()]
                );
        
        } else {
            $paginated = new LengthAwarePaginator([], 0, $perPage, $page);            
        }

        return Inertia::render('payments/Payments', [
            'payments' => [
                'data' => $paginated->items(),
                'links' => $paginated->linkCollection()->map(function($link){
                    if ($link['label'] === '&laquo; Previous') $link['label'] = 'Prev';
                    if ($link['label'] === 'Next &raquo;') $link['label'] = 'Next';
                    return $link;
                }),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                ]
            ]
        ]);
    }

}
