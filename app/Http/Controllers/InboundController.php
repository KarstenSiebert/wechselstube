<?php

namespace App\Http\Controllers;

use DB;
use Exception;
use Inertia\Inertia;
use App\Models\Inbound;
use App\Rules\TokenPolicy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Helpers\CardanoFingerprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class InboundController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $inbounds = Inbound::where('user_id', auth()->id())->where('is_active', true)->paginate(10);
        
        $inboundList = [];

        foreach ($inbounds as $inbound) {

            if ((strtoupper($inbound->inbound_token) !== 'ADA') && !empty($inbound->policy_id) && !empty($inbound->inbound_hex)) {                       
                $is_CIP68 = false;
                $is_NFTTK = false;
                
                $nameHex  = '';
              
                if (str_starts_with($inbound->inbound_hex, '0014df10')) {
                    $nameHex = substr($inbound->inbound_hex, 8);
                    $is_CIP68 = true;  

                } else if (str_starts_with($inbound->inbound_hex, '000643b0')) {
                    $nameHex = substr($inbound->inbound_hex, 8);
                    $is_CIP68 = true;
                    $is_NFTTK = true;

                } else {
                    $nameHex = $inbound->inbound_hex;
                }
              
                $predefined = [
                    'USDM'          => ['policy' => 'c48cbb3d5e57ed56e276bc45f99ab39abe94e6cd7ac39fb402da47ad', 'logo' => 'https://www.wechselstuben.net/storage/logos/usdm-coin.png', 'decimals' => 6],
                    'USDA'          => ['policy' => 'fe7c786ab321f41c654ef6c1af7b3250a613c24e4213e0425a7ae456', 'logo' => 'https://www.wechselstuben.net/storage/logos/usda-coin.png', 'decimals' => 6],
                    'DjedMicroUSD'  => ['policy' => '8db269c3ec630e06ae29f74bc39edd1f87c819f1056206e879a1cd61', 'logo' => 'https://www.wechselstuben.net/storage/logos/djed-coin.png', 'decimals' => 6],
                    'Wechselstuben' => ['policy' => '8dfd68762a95e06f3d66c04f2a688241767e8ea934a4144b4915a681', 'logo' => 'https://www.wechselstuben.net/storage/logos/wechselstuben-logo.png', 'decimals' => str_starts_with($inbound->inbound_hex, '0014df10') ? 6 : 0]
                ];
                                                
                if (isset($predefined[$inbound->inbound_token]) && $predefined[$inbound->inbound_token]['policy'] === $inbound->policy_id) {
                    $img = $predefined[$inbound->inbound_token]['logo'];
                    $inbound->decimals = $predefined[$inbound->inbound_token]['decimals'];

                } else {
                    $policyId = $inbound->policy_id;

                    $cacheKey = 'jsonMeta_' . $nameHex.$policyId;

                    $jsonMeta = Cache::remember($cacheKey, 3600, function () use ($nameHex, $policyId) {
                        return CardanoFingerprint::getTokenJson($nameHex, $policyId);
                    });   
                        
                    if (!empty($jsonMeta)) {                    
                        $inbound->decimals = !empty($jsonMeta['decimals']) ? $jsonMeta['decimals'] : 0;

                        $inbound->logo_url = !empty($jsonMeta['image']) ? $jsonMeta['image'] : '';

                        if (!empty($inbound->logo_url)) {
                            $img = $inbound->logo_url;
                        }                    
                    }
                }
            
                if (empty($img)) {
                    $img = 'https://www.wechselstuben.net/storage/logos/wechselstuben-logo.png';
                }

            } else {                    
                $img = 'https://www.wechselstuben.net/storage/logos/cardano-ada-logo.png';
                
                $inbound->fingerprint = null;
                $inbound->decimals    = 6;                   
            }

            $inboundList[] = ['id'            => $inbound->id,
                              'inbound_token' => $inbound->inbound_token,
                              'location'      => $inbound->location, 
                              'fingerprint'   => $inbound->fingerprint, 
                              'cost'          => $inbound->cost,
                              'decimals'      => $inbound->decimals,
                              'hash'          => $inbound->hash,
                              'logo_url'      => $img
            ];
        }
        
        $links = $inbounds->linkCollection()->map(function ($link) {

            if ($link['label'] === '&laquo; Previous') {
                $link['label'] = 'Prev';

            } elseif ($link['label'] === 'Next &raquo;') {
                $link['label'] = 'Next';
            }
            
            return $link;
        });

        $totalPages = $links->filter(fn($link) => is_numeric($link['label']))->count();

        $currentPage = $links->firstWhere('active', true)['page'] ?? 1;

        $links = $links->filter(function ($link) use ($totalPages, $currentPage) {
    
            if ($totalPages <= 1 && in_array($link['label'], [__('Prev'), __('Next')])) {
                return false;
            }

            if ($link['label'] === __('Prev') && $currentPage === 1) {
                return false;
            }

            if ($link['label'] === __('Next') && $currentPage === $totalPages) {
                return false;
            }

            return true;

        })->values();
                
        return Inertia::render('inbounds/Inbounds', [
            'inbounds' => [
                'data' => $inboundList,
                'links' => $links,
                'meta'  => [
                    'current_page' => $inbounds->currentPage(),
                    'last_page' => $inbounds->lastPage(),
                    'per_page' => $inbounds->perPage(),
                    'total' => $inbounds->total(),
                ]
            ]
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('inbounds/Create', [
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate(
            [
                'location' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('inbounds')->where(
                        fn ($query) => $query->where('user_id', auth()->id())->where('is_active', true)
                    ),
                ],
                'inbound_token' => ['required', 'string', 'max:32'],
                'cost' => ['required', 'integer', 'gt:0'],
            ],
            [
                'inbound_token.required' => __('validation.please_provide_a_valid_token_name'),
                'location.unique'        => __('validation.this_location_already_exists_for_your_account'),
                'inbound_token.max'      => __('validation.the_token_must_not_be_longer_than_32_characters')
            ]
        );

        try {
            $is_CIP68 = $request->is_CIP68;

            $fingerprint = CardanoFingerprint::fromPolicyAndName($request->policy_id, $request->inbound_token, $is_CIP68);
            
            $decimals = (!empty($request->decimals) && ($request->decimals < 7)) ? $request->decimals : 0;

            $inboundHex = ($is_CIP68 === true) ? '0014df10'.bin2hex($request->inbound_token) : bin2hex($request->inbound_token);

            $location = trim($request->location);

            $hash = substr(hash('sha256', openssl_random_pseudo_bytes(1024)), 0, 32);
                        
            try {
                $inbound = Inbound::create([
                    'user_id'       => auth()->id(),
                    'inbound_token' => $request->inbound_token,
                    'inbound_hex'   => $inboundHex,
                    'policy_id'     => $request->policy_id,
                    'fingerprint'   => $fingerprint,
                    'location'      => $location,
                    'cost'          => $request->cost,
                    'decimals'      => $decimals,
                    'hash'          => $hash,
                    'is_active'     => true
                ]);
            
                return redirect('inbounds')->with('success', __('validation.token_successfully_created_as_inbound_cost'));
         
            } catch (Exception $e) {            
                return redirect('inbounds')->with('error', $e->getMessage());
            }        

        } catch (Exception $e) {            
            return redirect('inbounds')->with('error', $e->getMessage());
        }
    
        return redirect('inbounds')->with('error', __('validation.token_not_created_as_inbound_cost'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Inbound $inbound)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Inbound $inbound)
    {
        $inbound = ['id' => $inbound->id,
                    'inbound_token' => $inbound->inbound_token,
                    'inbound_hex' => $inbound->inbound_hex,
                    'location' => $inbound->location,
                    'policy_id' => $inbound->policy_id,
                    'fingerprint' => $inbound->fingerprint,
                    'cost' => $inbound->cost,
                    'decimals' => $inbound->decimals
                    ];

        return Inertia::render('inbounds/Edit', [
            'inbound' => $inbound
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Inbound $inbound)
    {
        $validated = $request->validate([            
                'cost' => ['required', 'integer', 'gt:0']
            ]
        );

        $error   = 'error';
        $message = 'validation.inbound_cost_not_updated';

        if ($inbound = Inbound::where('id', $inbound->id)->where('user_id', auth()->id())->where('is_active', true)->update($validated)) {
            $error   = 'success';
            $message = 'validation.inbound_cost_updated_successfully';
        }
        
        return redirect('inbounds')->with($error, __($message));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Inbound $inbound)
    {
        $error   = 'error';
        $message = 'validation.inbound_cost_not_deleted';

        if (Inbound::where('id', $inbound->id)->where('user_id', auth()->id())->where('is_active', true)->update(['is_active' => false])) {

        // if (Inbound::where('id', $inbound->id)->where('user_id', auth()->id())->where('is_active', true)->delete()) {
            $error   = 'success';
            $message = 'validation.inbound_cost_deleted_successfully';
        }

        return back()->with($error, __($message));
    }

    public function search(Request $request)
    {        
        $q = $request->input('q', '');
        
        $inbounds = Inbound::query()
            ->where('is_active', true)
            ->where(function ($query) use ($q) {
                $query->where('inbound_token', 'like', "%{$q}%")
                    ->orWhere('policy_id', 'like', "%{$q}%")
                    ->orWhere('loction', 'like', "%{$q}%")
                    ->orWhere('fingerprint', 'like', "%{$q}%");
            })
            ->select('inbound_token', 'policy_id', 'fingerprint', 'location', 'cost', 'decimals')
            ->limit(10)
            ->get(['id', 'inbound_token', 'policy_id', 'fingerprint', 'location', 'cost', 'decimals']);

        $selected = [];

        foreach($inbounds as $inbound) {            
            $selected[] = $inbound;
        }
            
        return response()->json($selected);
    }

    public function qrcode(Request $request): string
    {
        $hash = !empty($request->input('hash')) ? trim($request->input('hash')) : '';
    
        if (!empty($hash)) {
            $qrCode = "data:image/png;base64,".base64_encode(QrCode::size(234)
                    ->format('png')
                    // ->color(148, 164, 163)
                    ->errorCorrection('H')
                    ->margin(1)
                    ->encoding('UTF-8')
                    ->generate($hash));
        
            return $qrCode;
        }

        return '';
    }

}
