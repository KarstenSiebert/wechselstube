<?php

namespace App\Http\Controllers;

use DB;
use Exception;
use Inertia\Inertia;
use App\Models\BabelFee;
use App\Rules\TokenPolicy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Helpers\CardanoFingerprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class BabelFeeController extends Controller
{
     public function index() 
    {        
        $babelfees = BabelFee::where('user_id', auth()->id())->paginate(10);

        $address = auth()->user()->wallet->address;

        $babelfeeList = [];
        
        foreach ($babelfees as $babelfee) {

            $predefined = [
                'USDM'          => ['policy' => 'c48cbb3d5e57ed56e276bc45f99ab39abe94e6cd7ac39fb402da47ad', 'logo' => 'https://www.wechselstuben.net/storage/logos/usdm-coin.png', 'decimals' => 6],
                'USDA'          => ['policy' => 'fe7c786ab321f41c654ef6c1af7b3250a613c24e4213e0425a7ae456', 'logo' => 'https://www.wechselstuben.net/storage/logos/usda-coin.png', 'decimals' => 6],
                'DjedMicroUSD'  => ['policy' => '8db269c3ec630e06ae29f74bc39edd1f87c819f1056206e879a1cd61', 'logo' => 'https://www.wechselstuben.net/storage/logos/djed-coin.png', 'decimals' => 6],
                'Wechselstuben' => ['policy' => '8dfd68762a95e06f3d66c04f2a688241767e8ea934a4144b4915a681', 'logo' => 'https://www.wechselstuben.net/storage/logos/wechselstuben-logo.png', 'decimals' => str_starts_with($babelfee->babelfee_hex, '0014df10') ? 6 : 0]
            ];

            if (isset($predefined[$babelfee->babelfee_token]) && $predefined[$babelfee->babelfee_token]['policy'] === $babelfee->policy_id) {
                $img = $predefined[$babelfee->babelfee_token]['logo'];
            
            } else {
                $assetHex = bin2hex($babelfee->babelfee_token);
                $policyId = $babelfee->policy_id;

                $cacheKey = 'img_' . $assetHex.$policyId;

                $img = Cache::remember($cacheKey, 3600, function () use ($assetHex, $policyId) {                        
                    return CardanoFingerprint::getTokenLogo($assetHex, $policyId);
                });
            }
            
            if (empty($img)) {
                $img = 'https://www.wechselstuben.net/storage/logos/wechselstuben-logo.png';
            }

            $babelfeeList[] = ['id' => $babelfee->id,
                               'babelfee_token' => $babelfee->babelfee_token, 
                               'fingerprint'    => $babelfee->fingerprint, 
                               'rate'           => $babelfee->rate,
                               'logo_url'       => $img
            ];
        }

        $links = $babelfees->linkCollection()->map(function ($link) {

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
                
        return Inertia::render('babelfees/BabelFees', [
            'babelfees' => [
                'data' => $babelfeeList,
                'links' => $links,
                'meta'  => [
                    'current_page' => $babelfees->currentPage(),
                    'last_page' => $babelfees->lastPage(),
                    'per_page' => $babelfees->perPage(),
                    'total' => $babelfees->total(),
                ]
            ]
        ]);        
    }

    public function create() 
    {        
        return Inertia::render('babelfees/Create', [
        ]);
    }

    public function edit(BabelFee $babelfee)
    {                
        $babelFee = ['id' => $babelfee->id, 
                     'babelfee_token' => $babelfee->babelfee_token, 
                     'policy_id' => $babelfee->policy_id, 
                     'fingerprint' => $babelfee->fingerprint, 
                     'rate' => $babelfee->rate,
                     'decimals' => $babelfee->decimals
                    ];

        return Inertia::render('babelfees/Edit', [
            'babelfee' => $babelFee
        ]);
    }

    public function store(Request $request)
    {        
        $validated = $request->validate(
            [
                'babelfee_token' => [
                    'required',
                    'string',
                    'max:32',
                    Rule::unique('babel_fees')->where(
                        fn ($query) => $query->where('user_id', auth()->id())
                    ),
                ],
                'policy_id' => ['required', new TokenPolicy()],
                'rate' => [
                    'required',
                    'regex:/^\d+(\.\d{1,3})?$/',
                    function ($attribute, $value, $fail) {
                        if ((float) $value < 0.0) {
                            $fail(__("validation.rate_must_be_greater"));
                        }
                    },
                ],
                'decimals' => 'required|in:0,1,2,3,4,5,6',
            ],
            [
                'babelfee_token.required' => __('validation.please_provide_a_valid_token_name'),
                'babelfee_token.unique'   => __('validation.this_token_already_exists_for_your_account'),
                'babelfee_token.max'      => __('validation.the_token_must_not_be_longer_than_32_characters'),
                'decimals.required'       => __('validation.please_provide_a_valid_value_for_decimals'),
                'decimals.in'             => __('validation.one_of_0_1_2_3_4_5_6'),
            ]
        );

        try {
            $is_CIP68 = $request->is_CIP68;

            $fingerprint = CardanoFingerprint::fromPolicyAndName($request->policy_id, $request->babelfee_token, $is_CIP68);
            
            $decimals = (!empty($request->decimals) && ($request->decimals < 7)) ? $request->decimals : 0;
            
            try {
                $babelfee = BabelFee::create([
                    'user_id'        => auth()->id(),
                    'babelfee_token' => $request->babelfee_token,
                    'policy_id'      => $request->policy_id,
                    'fingerprint'    => $fingerprint,                    
                    'rate'           => $request->rate,
                    'decimals'       => $decimals
                ]);
            
                return redirect('babelfees')->with('success', __('token_successfully_created_as_babel_fee'));
         
            } catch (Exception $e) {            
                return redirect('babelfees')->with('error', $e->getMessage());
            }        

        } catch (Exception $e) {            
            return redirect('babelfees')->with('error', $e->getMessage());
        }
    
        return redirect('babelfees')->with('error', __('token_not_created_as_babel_fee'));
    }

    public function update(Request $request, BabelFee $babelfee)
    {
        $validated = $request->validate([            
                'rate' => [
                    'required',
                    'regex:/^\d+(\.\d{1,3})?$/',
                    function ($attribute, $value, $fail) {
                        if ((float) $value < 0.0) {
                            $fail(__('validation.rate_must_be_greater'));
                        }
                    },
                ],
            ]
        );

        $error   = 'error';
        $message = 'validation.babel_fee_not_updated';

        if ($babelfee = Babelfee::where('id', $babelfee->id)->where('user_id', auth()->id())->update($validated)) {
            $error   = 'success';
            $message = 'validation.babel_fee_updated_successfully';
        }
        
        return redirect('babelfees')->with($error, __($message));
    }
    
    public function destroy(BabelFee $babelfee)
    {
        $error   = 'error';
        $message = 'validation.babel_fee_not_deleted';

        if ($babelfee = Babelfee::where('id', $babelfee->id)->where('user_id', auth()->id())->delete()) {
            $error   = 'success';
            $message = 'validation.babel_fee_deleted_successfully';
        }

        return back()->with($error, __($message));
    }

    public function search(Request $request)
    {        
        $q = $request->input('q', '');
        
        $babelfees = Babelfee::query()
            ->where('is_active', true)
            ->where(function ($query) use ($q) {
                $query->where('babelfee_token', 'like', "%{$q}%")
                    ->orWhere('policy_id', 'like', "%{$q}%")
                    ->orWhere('fingerprint', 'like', "%{$q}%");
            })
            ->select('babelfee_token', 'policy_id', 'fingerprint')
            ->selectRaw('MAX(decimals) as decimals') 
            ->groupBy('babelfee_token', 'policy_id', 'fingerprint')
            ->limit(10)
            ->get(['id', 'babelfee_token', 'policy_id', 'fingerprint', 'decimals']);

        $selected = [];
        
        foreach($babelfees as $babelfee) {
            if ($babelfee['babelfee_token'] === 'USDM' || $babelfee['babelfee_token'] === 'Wechselstuben') {
                $babelfee['is_CIP68'] = true;
            
            } else {
                $babelfee['is_CIP68'] = false;
            }

            $selected[] = $babelfee;
        }

        return response()->json($selected);
    }

}
