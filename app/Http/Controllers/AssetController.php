<?php

namespace App\Http\Controllers;

use DB;
use Auth;
use Crypt;
use Exception;
use Inertia\Inertia;
use App\Models\Wallet;
use App\Models\InputOutput;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Helpers\CreateNftMetadata;
use App\Helpers\CardanoCliWrapper;
use App\Helpers\CardanoFingerprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Http\Controllers\TransactionController;


class AssetController extends Controller
{   
    private CardanoCliWrapper $cli;

    private function getAssetList(string $address, string $payment_token = '', bool $payment = false): array
    {
        $assets = DB::connection('cexplorer')->select("
                        SELECT                            
                            ma.name::text AS asset_name,
                            ma.name::text AS asset_hex,
                            ma.policy::text AS policy_id,                           
                            SUM(ma_tx_out.quantity) AS quantity
                        FROM tx_out AS tx_outer
                        LEFT JOIN ma_tx_out ON tx_outer.id = ma_tx_out.tx_out_id
                        LEFT JOIN multi_asset ma ON ma.id = ma_tx_out.ident
                        WHERE tx_outer.address = ?
                        AND NOT EXISTS (
                            SELECT 1
                            FROM tx_out t2
                            INNER JOIN tx_in ti 
                            ON t2.tx_id = ti.tx_out_id AND t2.index = ti.tx_out_index
                            WHERE t2.id = tx_outer.id
                        )
                        GROUP BY ma.policy, ma.name

                        UNION ALL

                        SELECT
                            'ADA' AS asset_name,
                            NULL AS asset_hex,
                            NULL AS policy_id,                           
                            SUM(value) AS quantity
                        FROM tx_out AS tx_outer
                        WHERE tx_outer.address = ?
                        AND NOT EXISTS (
                            SELECT 1
                            FROM tx_out t2
                            INNER JOIN tx_in ti 
                            ON t2.tx_id = ti.tx_out_id AND t2.index = ti.tx_out_index
                            WHERE t2.id = tx_outer.id
                        )
                        ORDER BY asset_name LIMIT 100;
                        ",
                        [$address, $address]
        );
      
        $assetList = [];

        foreach($assets as $asset) {
            if ($asset->quantity) {

                $img = null;

                $asset->decimals = 0;
                
                if ((strtoupper($asset->asset_name) !== 'ADA') && !empty($asset->policy_id) && !empty($asset->asset_hex)) {
                    $is_CIP68 = false;
                    $is_NFTTK = false;
                       
                    if (str_starts_with($asset->asset_hex, '\x0014df10')) {                        
                        $asset->asset_name = hex2bin(substr($asset->asset_hex, 10));
                        $is_CIP68 = true;

                    } else if (str_starts_with($asset->asset_hex, '\x000643b0')) {
                        $asset->asset_name = hex2bin(substr($asset->asset_hex, 10));                        
                        $is_CIP68 = true;
                        $is_NFTTK = true;

                    } else {                        
                        $asset->asset_name = hex2bin(substr($asset->asset_hex, 2));
                    }
                        
                    $asset->policy_id = substr($asset->policy_id, 2);
                    $asset->asset_hex = substr($asset->asset_hex, 2);    
                    
                    $asset->fingerprint = CardanoFingerprint::fromPolicyAndName($asset->policy_id, $asset->asset_name, $is_CIP68, $is_NFTTK);
                                
                    $predefined = [
                        'USDM'          => ['policy' => 'c48cbb3d5e57ed56e276bc45f99ab39abe94e6cd7ac39fb402da47ad', 'logo' => 'https://www.wechselstuben.net/storage/logos/usdm-coin.png', 'decimals' => 6],
                        'USDA'          => ['policy' => 'fe7c786ab321f41c654ef6c1af7b3250a613c24e4213e0425a7ae456', 'logo' => 'https://www.wechselstuben.net/storage/logos/usda-coin.png', 'decimals' => 6],
                        'DjedMicroUSD'  => ['policy' => '8db269c3ec630e06ae29f74bc39edd1f87c819f1056206e879a1cd61', 'logo' => 'https://www.wechselstuben.net/storage/logos/djed-coin.png', 'decimals' => 6],
                        'Wechselstuben' => ['policy' => '8dfd68762a95e06f3d66c04f2a688241767e8ea934a4144b4915a681', 'logo' => 'https://www.wechselstuben.net/storage/logos/wechselstuben-logo.png', 'decimals' => str_starts_with($asset->asset_hex, '0014df10') ? 6 : 0]
                    ];

                    if (isset($predefined[$asset->asset_name]) && $predefined[$asset->asset_name]['policy'] === $asset->policy_id) {
                        $img = $predefined[$asset->asset_name]['logo'];
                        $asset->decimals = $predefined[$asset->asset_name]['decimals'];

                    } else {                        
                        $assetHex = $asset->asset_hex;
                        $policyId = $asset->policy_id;

                        $cacheKey = 'jsonMeta_' . $assetHex.$policyId;

                        $jsonMeta = Cache::remember($cacheKey, 3600, function () use ($assetHex, $policyId) {
                            return CardanoFingerprint::getTokenJson($assetHex, $policyId);
                        });
                        
                        if (!empty($jsonMeta)) {
                            $asset->ticker = !empty($jsonMeta['ticker']) ? $jsonMeta['ticker'] : '';

                            $asset->decimals = !empty($jsonMeta['decimals']) ? $jsonMeta['decimals'] : 0;

                            $asset->description = !empty($jsonMeta['description']) ? $jsonMeta['description'] : '';

                            $asset->logo_url = !empty($jsonMeta['image']) ? $jsonMeta['image'] : '';

                            if (!empty($asset->logo_url)) {
                                $img = $asset->logo_url;
                            }
                        }                        
                    }
            
                    if (empty($img)) {
                        $img = 'https://www.wechselstuben.net/storage/logos/wechselstuben-logo.png';
                    }

                } else {                    
                    $img = 'https://www.wechselstuben.net/storage/logos/cardano-ada-logo.png';
                
                    $asset->fingerprint = null;
                    $asset->decimals    = 6;                   
                }

                $asset->logo_url = $img;

                if (empty($payment_token)) {
                    $assetList[] = $asset;

                } else if ($payment_token == $asset->asset_name) {
                    $assetList[] = $asset;
                }  

                /*
                if ($payment === true) {
                    if ($asset->quantity > 1) {
                        if (empty($payment_token)) {
                            $assetList[] = $asset;

                        } else if ($payment_token == $asset->asset_name) {
                            $assetList[] = $asset;
                        }
                    }
                } else {
                    if (empty($payment_token)) {
                        $assetList[] = $asset;

                    } else if ($payment_token == $asset->asset_name) {
                        $assetList[] = $asset;
                    }
                }
                */
            }
        }

        return $assetList;
    }

    public function index(Request $request)
    {
        $search = $request->input('search', null);

        $payment_token = $request->input('payment_token', null);
    
        $page = (int) $request->input('page', 1);
    
        $perPage = 10;

        $address = Auth::user()->wallet->address;
    
        if ($address !== null) {                           
            $allAssets = Cache::tags(['user:' . Auth::id()])->remember('assets', 60, function () use ($address) {

                $assetsQuery1 = DB::connection('cexplorer')->table('tx_out as tx_outer')
                    ->leftJoin('ma_tx_out', 'tx_outer.id', '=', 'ma_tx_out.tx_out_id')
                    ->leftJoin('multi_asset as ma', 'ma.id', '=', 'ma_tx_out.ident')
                    ->selectRaw('ma.name::text AS asset_name, ma.name::text AS asset_hex, ma.policy::text AS policy_id, SUM(ma_tx_out.quantity) AS quantity')
                    ->where('tx_outer.address', $address)
                    ->whereNotNull('ma.name')
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('tx_out as t2')
                            ->join('tx_in as ti', function($join){
                                $join->on('t2.tx_id', '=', 'ti.tx_out_id')
                                     ->on('t2.index', '=', 'ti.tx_out_index');
                            })
                            ->whereColumn('t2.id', 'tx_outer.id');
                    })
                    ->groupBy('ma.policy', 'ma.name');

                $assetsQuery2 = DB::connection('cexplorer')->table('tx_out as tx_outer')
                    ->selectRaw("'ADA' AS asset_name, NULL AS asset_hex, NULL AS policy_id, SUM(value) AS quantity")
                    ->where('tx_outer.address', $address)
                    ->whereNotExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('tx_out as t2')
                            ->join('tx_in as ti', function($join){
                                $join->on('t2.tx_id', '=', 'ti.tx_out_id')
                                     ->on('t2.index', '=', 'ti.tx_out_index');
                            })
                            ->whereColumn('t2.id', 'tx_outer.id');
                    });

                return $assetsQuery1->unionAll($assetsQuery2)
                                ->orderBy('asset_name')
                                ->get();
            });

            $searchTerms = preg_split('/\+/', strtolower(trim($search)));

            $filteredAssets = $allAssets->filter(function($asset) use ($searchTerms) {

                if (!$searchTerms) return true;
    
                $assetName = 'ADA';

                if ($asset->asset_name !== 'ADA') {
                    if (str_contains($asset->asset_name, '\x0014df10') || str_contains($asset->asset_name, '\x000643b0')) {
                        $assetName = hex2bin(substr($asset->asset_name, 10));
        
                    } else {
                        $assetName = hex2bin(substr($asset->asset_name, 2));
                    }
                }

                $assetName   = strtolower($assetName);
                $fingerprint = strtolower($asset->fingerprint ?? '');
    
                return collect($searchTerms)->contains(fn($term) =>
                    str_contains($assetName, $term) || str_contains($fingerprint, $term)
                );
            });

            $assetList = $filteredAssets->map(function($asset) use ($payment_token) {

                if (!$asset->quantity) return null;

                $img = null;

                $asset->decimals = 0;
                    
                if ((strtoupper($asset->asset_name) !== 'ADA') && !empty($asset->policy_id) && !empty($asset->asset_hex)) {
                    $is_CIP68 = false;
                    $is_NFTTK = false;
                    
                    if (str_starts_with($asset->asset_hex, '\x0014df10')) {
                        $asset->asset_name = hex2bin(substr($asset->asset_hex, 10));
                        $is_CIP68 = true;  

                    } else if (str_starts_with($asset->asset_hex, '\x000643b0')) {
                        $asset->asset_name = hex2bin(substr($asset->asset_hex, 10));
                        $is_CIP68 = true;
                        $is_NFTTK = true;
                        
                    } else {
                        $asset->asset_name = hex2bin(substr($asset->asset_hex, 2));
                    }
                        
                    $asset->policy_id = substr($asset->policy_id, 2);
                    $asset->asset_hex = substr($asset->asset_hex, 2);

                    $asset->fingerprint = CardanoFingerprint::fromPolicyAndName($asset->policy_id, $asset->asset_name, $is_CIP68, $is_NFTTK);
                                          
                    $predefined = [
                        'USDM'          => ['policy' => 'c48cbb3d5e57ed56e276bc45f99ab39abe94e6cd7ac39fb402da47ad', 'logo' => 'https://www.wechselstuben.net/storage/logos/usdm-coin.png', 'decimals' => 6],
                        'USDA'          => ['policy' => 'fe7c786ab321f41c654ef6c1af7b3250a613c24e4213e0425a7ae456', 'logo' => 'https://www.wechselstuben.net/storage/logos/usda-coin.png', 'decimals' => 6],
                        'DjedMicroUSD'  => ['policy' => '8db269c3ec630e06ae29f74bc39edd1f87c819f1056206e879a1cd61', 'logo' => 'https://www.wechselstuben.net/storage/logos/djed-coin.png', 'decimals' => 6],
                        'Wechselstuben' => ['policy' => '8dfd68762a95e06f3d66c04f2a688241767e8ea934a4144b4915a681', 'logo' => 'https://www.wechselstuben.net/storage/logos/wechselstuben-logo.png', 'decimals' => str_starts_with($asset->asset_hex, '0014df10') ? 6 : 0]
                    ];

                    if (isset($predefined[$asset->asset_name]) && $predefined[$asset->asset_name]['policy'] === $asset->policy_id) {
                        $img = $predefined[$asset->asset_name]['logo'];
                        $asset->decimals = $predefined[$asset->asset_name]['decimals'];

                    } else {                    
                        $assetHex = $asset->asset_hex;
                        $policyId = $asset->policy_id;

                        $cacheKey = 'jsonMeta_' . $assetHex.$policyId;

                        $jsonMeta = Cache::remember($cacheKey, 3600, function () use ($assetHex, $policyId) {
                            return CardanoFingerprint::getTokenJson($assetHex, $policyId);
                        });                        
                        
                        if (!empty($jsonMeta)) {
                            $asset->ticker = !empty($jsonMeta['ticker']) ? $jsonMeta['ticker'] : '';

                            $asset->decimals = !empty($jsonMeta['decimals']) ? $jsonMeta['decimals'] : 0;

                            $asset->description = !empty($jsonMeta['description']) ? $jsonMeta['description'] : '';

                            $asset->logo_url = !empty($jsonMeta['image']) ? $jsonMeta['image'] : '';

                            if (!empty($asset->logo_url)) {
                                $img = $asset->logo_url;
                            }
                        }                            
                    }
            
                    if (empty($img)) {
                        $img = 'https://www.wechselstuben.net/storage/logos/wechselstuben-logo.png';
                    }

                } else {
                    $img = 'https://www.wechselstuben.net/storage/logos/cardano-ada-logo.png';

                    $asset->fingerprint = null;
                    $asset->decimals    = 6;
                }

                $asset->logo_url = $img;
                
                if ($payment_token && $payment_token !== $asset->asset_name) return null;
                            
                return $asset;

            })->filter()->values();
            
            $paginated = new LengthAwarePaginator(
                $assetList->forPage($page, $perPage)->values(),
                $assetList->count(),
                $perPage,
                $page,
                ['path' => request()->url(), 'query' => $request->query()]
            );

        } else {
            $paginated = new LengthAwarePaginator([], 0, $perPage, $page);
        }
        
        return Inertia::render('assets/Assets', [
            'assets' => [
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

    public function store(Request $request)
    {   
        $validated = $request->validate([
            'selected_assets' => 'required|array|min:1',
            'selected_assets.*.policy_id' => 'nullable|string',
            'selected_assets.*.asset_name' => 'required|string',
            'selected_assets.*.address' => 'nullable|string',
            'selected_assets.*.asset_hex' => 'nullable|string',
            'selected_assets.*.fingerprint' => 'nullable|string',
            'selected_assets.*.quantity' => 'required|numeric|min:1',
            'selected_assets.*.decimals' => 'required|integer|min:0',
            'selected_assets.*.logo_url' => 'nullable|string',
        ]);

        $assets = $validated['selected_assets'];

        $preparedAssets = [];
        
        foreach($assets as $asset) {
            $token = 0;
         
            if (!empty($asset['address'])) {
                $toknm = bcdiv($asset['quantity'], bcpow("10", (string) $asset['decimals']), $asset['decimals']);
            
                $token = $toknm ? $toknm : 0;
            
            } else {
                if ($asset['quantity'] == 1) {
                    $token = 1;
                }
            }

            $asset['address'] = !empty($asset['address']) ? $asset['address'] : null;
            
            $preparedAssets[] = ['asset_name'   => $asset['asset_name'],                                  
                                 'asset_hex'    => $asset['asset_hex'],
                                 'policy_id'    => $asset['policy_id'],
                                 'fingerprint'  => $asset['fingerprint'],
                                 'quantity'     => $asset['quantity'],
                                 'decimals'     => $asset['decimals'],                                 
                                 'token_number' => $token,
                                 'logo_url'     => $asset['logo_url'],
                                 'destination'  => $asset['address']
            ];
        };
        
        return Inertia::render('assets/Submit', [
            'assets' => $preparedAssets
        ]);
    }

    public function transfer(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'selected_assets' => 'required|array|min:1',
            'selected_assets.*.policy_id' => 'nullable|string',
            'selected_assets.*.asset_name' => 'required|string',
            'selected_assets.*.asset_hex' => 'nullable|string',
            'selected_assets.*.fingerprint' => 'nullable|string',
            'selected_assets.*.destination' => 'required|string',
            'selected_assets.*.logo_url' => 'nullable|string',
            'selected_assets.*.token_number' => 'required|numeric|min:1',
            'selected_assets.*.decimals' => 'required|integer|min:0',
        ]);
 
        if ($validator->fails()) {
            return Inertia::render('assets/Submit', [
                'assets' => $request->selected_assets
            ]);
        }

        $data = $request->input('selected_assets');
        
        $assets = [];

        $address = Auth::user()->wallet->address;
        
        if ($address !== null) {
            $assets = $this->getAssetList($address, '', true);
        }
   
        $minRates = DB::table('babel_fees as bf1')
            ->select('bf1.fingerprint', 'bf1.rate', 'bf1.decimals', 'users.name as provider_name', 'wallets.address')
            ->join('users', 'bf1.user_id', '=', 'users.id')
            ->join('wallets', 'wallets.user_id', '=', 'users.id')
            ->where('bf1.is_active', true)
            ->where('users.id', '<>', auth()->id())
            ->whereIn('bf1.rate', function ($query) {
                $query->selectRaw('MIN(bf2.rate)')
                    ->from('babel_fees as bf2')
                    ->whereColumn('bf2.fingerprint', 'bf1.fingerprint')
                    ->where('bf2.is_active', true)
                    ->where('bf2.user_id', '<>', auth()->id());
            })
            ->get();
    
        // dd($minRates);

        // dd($assets);

        $assets = collect($assets)->flatMap(function ($asset) use ($minRates) {
            $asset = (object) $asset;

            $rateInfos = $asset->fingerprint ? $minRates->where('fingerprint', $asset->fingerprint) : collect();

            if ($rateInfos->isNotEmpty()) {
                return $rateInfos->map(function ($r) use ($asset) {
            
                    return [
                        'asset_name' => $asset->asset_name,
                        'asset_hex' => $asset->asset_hex,
                        'policy_id' => $asset->policy_id ?? null,
                        'fingerprint' => $asset->fingerprint,
                        'quantity' => $asset->quantity,
                        'logo_url' => $asset->logo_url,
                        'provider_name' => $r->provider_name,
                        'address' => $r->address,
                        'decimals' => $r->decimals,
                        'rate' => $r->rate
                    ];
                });
            } else {        
                return [[
                    'asset_name' => $asset->asset_name,
                    'asset_hex' => $asset->asset_hex,
                    'policy_id' => $asset->policy_id ?? null,
                    'fingerprint' => $asset->fingerprint,
                    'quantity' => $asset->quantity,
                    'logo_url' => $asset->logo_url,
                    'provider_name' => null,
                    'address' => null,
                    'decimals' => null,
                    'rate' => null
                ]];
            }
        })->values();
        
        $data = collect($data);

        if ($data->contains('asset_name', 'ADA')) {
            $assets = $assets->where('asset_name', 'ADA')->values();
        }
        
        $filtered = $assets->reject(function ($item) {
            return ($item['rate'] === '0.000') || (($item['rate'] === null) && ($item['asset_name'] !== 'ADA'));
        })->values()->toArray();
        
        return Inertia::render('assets/Payment', [
            'selected_assets'  => $data,
            'available_assets' => $filtered
        ]);       
    }

    public function confirm(Request $request)
    {
        $err = 'error';       
   
        $transaction = $request->input('transaction');

        $txPrefix = $transaction['tx_prefix'];
        $txFee    = $transaction['tx_fee'];

        if (!empty($txPrefix)) {
            $this->cli = new CardanoCliWrapper($txPrefix, '/usr/local/bin/cardano-cli', '/tmp/node.socket');
      
            if (file_exists($this->cli->txPrefix.'matx.signed')) {
                                
                $retVal = $this->cli->submitTransaction();

                if (strlen($retVal) == 64) {
                    
                    Transaction::Create([
                        'user_id' => Auth::id(),
                        'transaction_id' => $retVal,
                        'transaction_fee' => floatval($txFee)
                    ]);
                            
                    $err = 'success';
                                        
                    Cache::tags(['user:' . Auth::id()])->flush();                    
                }
            }

            $this->remove_dir($txPrefix);
        }

        $message = ($err == 'error') ? __('transaction_not_submitted') : __('transaction_successfully_submitted');
        
        return redirect('assets')->with($err, $message);
    }

    public function payment(Request $request)
    {
        // dd($request->all());

        $validator = Validator::make($request->all(), [
            'chosen_asset.policy_id' => 'nullable|string',
            'chosen_asset.asset_name' => 'required|string',
            'chosen_asset.asset_hex' => 'nullable|string',
            'chosen_asset.provider_name' => 'nullable|string',
            'chosen_asset.address' => 'nullable|string',
            'chosen_asset.decimals' => 'nullable|integer',
            'chosen_asset.rate' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return Inertia::render('assets/Submit', [
                'assets' => $request->input('selected_assets')
            ]);            
        }
        
        $data = $request->input('selected_assets');

        $metaData = new CreateNftMetadata();
        
        $additional_info = !empty($request->input('additional_info')) ? $request->input('additional_info') : '';
        
        $nftMetaData = $metaData->generateTransactionMetadata($additional_info);

        $address = Auth::user()->wallet->address;

        $txPrefix = '/var/www/stube/storage/app/private/transactions/'.bin2hex(openssl_random_pseudo_bytes(4)).'/';
      
        $asset = $request->get('chosen_asset');

        $asset_name = $asset['asset_name'];

        $err = [];
        
        $err['response'] = 'error';
        $err['message'] = '';
        
        $spentFee = 0;
        $netwkFee = 0;
        
        $change = $address;
        
        if (strtoupper($asset_name) === 'ADA') {

            if ($this->make_dir($txPrefix)) {
                $err = app(TransactionController::class)->generate_transaction($data, $change, $txPrefix, $nftMetaData, $spentFee, $netwkFee);
            }
        } else {
            if (!empty($asset['address']) && !empty($asset['rate']) && !empty($asset['asset_hex']) && !empty($asset['policy_id'])) {
            
                if ($this->make_dir($txPrefix)) {
                    $change = $asset['address'];

                    // dd($asset['decimals']);

                    $transferFee = 200000;

                    // dd($data, $asset);
                                        
                    $err = app(BabelTransactionController::class)->generate_transaction($data, $address, $change, $asset['policy_id'], $asset['asset_hex'], $asset['rate'], $asset['decimals'], $txPrefix, $nftMetaData, $spentFee, $netwkFee, $transferFee);

                    // $err = app(BabelTransactionController::class)->build_hydra_transaction($data, $address, $change, $asset['policy_id'], $asset['asset_hex'], $asset['rate'], $asset['decimals'], $txPrefix, $nftMetaData, $spentFee, $netwkFee, $transferFee);
                }
            }
        }

        if (empty($err['response'])) {
            $err['response'] = 'error';
        }
        
        if (!empty($err) && !empty($err['response'])) {
            
            if (!empty($err['message'])) {
                $err['message'] = ($err['response'] == 'error') ? $err['message'] : 'Transaction successfully assembled.';

            } else {
                $err['message'] = ($err['response'] == 'error') ? 'Error assembling transaction.' : 'Transaction successfully assembled.';
            }
        }
                
        // dd($err, $spentFee, $txPrefix);

        if (!empty($err['response']) && ($err['response'] == 'success')) {
            $transaction = [];
            $parsedvalue = [];

            $transaction['tx_fee']    = $spentFee;
            $transaction['tx_net']    = $netwkFee;
            $transaction['tx_prefix'] = $txPrefix;
            
            $transaction['tx_rate']   = $asset['rate'] ? $asset['rate'] : null;

            if ($inputOutputs = InputOutput::where('user_id', Auth::user()->id)->where('change', $change)->orderby('created_at', 'desc')->first()) {            
                $unparsedvalue = explode('--tx-out ', $inputOutputs->outputs);

                foreach($unparsedvalue as $val) {
                    if (!empty($val)) {
                        $parsedvalue[] = $val;
                    }
                }

                $parsed = [];

                foreach ($parsedvalue as $entry) {
                    [$address, $rest] = explode('+', $entry, 2);

                    preg_match('/^(\d+)/', $rest, $matches);
                    $lovelace = $matches[1] ?? 0;

                    preg_match_all('/"([^"]+)"/', $rest, $matches);
                    $tokens = [];
        
                    foreach ($matches[1] as $tokenStr) {
                        if (preg_match('/^(\d+)\s+([^.]+)\.(.+)$/', $tokenStr, $parts)) {
                            $qty = (int)$parts[1];
                            $policy = $parts[2];
                            $assetHex = $parts[3];
                            
                            if (str_starts_with($assetHex, '0014df10')) {
                                $assetName = hex2bin(substr($assetHex, 8));
                            } else {
                                $assetName = hex2bin($assetHex);
                            }
                
                            $tokens[] = [
                                'quantity'   => $qty,
                                'policy_id'  => $policy,
                                'asset_hex'  => $assetHex,
                                'asset_name' => $assetName,
                            ];
                        }
                    }

                    if ($address === Auth::user()->wallet->address) {
                        // $address = 'self';
                        $address = $address.' (self)';
                    }

                    $parsed[] = [
                        'address'  => $address,
                        'ada'      => $lovelace / 1_000_000,
                        'tokens'   => $tokens,                        ];                    
                }                
            }

            return Inertia::render('assets/Confirm', [
                'transaction' => $transaction,                
                'utxos'       => $parsed
            ]);
        }
        
        return redirect('assets')->with($err['response'], $err['message']);
    }

    private function make_dir($path)
    {
        return is_dir($path) || mkdir($path);
    }

    private function remove_dir($dir) : bool
    {
        $done = false;

        try {
            if (is_dir($dir)) {
                $objects = scandir($dir);

                foreach ($objects as $object) {
                    if ($object != '.' && $object != '..') {
                        unlink($dir . DIRECTORY_SEPARATOR . $object);
                    }
                }

                rmdir($dir);

                $done = true;
            }
        } catch (Exception $e) {
            
        }

        return $done;
    }

}
