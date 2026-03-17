<?php

namespace App\Http\Controllers;

use DB;
use Exception;
use Inertia\Inertia;
use App\Models\Mint;
use App\Models\TxOut;
use App\Models\BabelFee;
use App\Models\InputOutput;
use Illuminate\Http\Request;
use App\Helpers\CreateNftMetadata;
use App\Helpers\CardanoFingerprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Pagination\LengthAwarePaginator;

class MintController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {   
        $address = auth()->user()->wallet->address;

        $perPage = 10;
        $page = request()->get('page', 1);
        $offset = ($page - 1) * $perPage;

        $sql = "WITH encoded_assets AS (
                SELECT
                    ma.id,
                    encode(ma.policy, 'hex') AS policy_id,
                    encode(ma.name, 'hex')   AS asset_name
                FROM multi_asset ma
            ),
            minted_tokens AS (
                SELECT DISTINCT
                    ea.policy_id,
                    ea.asset_name,                    
                    ma_tx_mint.tx_id AS mint_tx_id
                FROM encoded_assets AS ea
                JOIN ma_tx_mint ON ma_tx_mint.ident = ea.id
                JOIN tx_out tx_outer ON tx_outer.tx_id = ma_tx_mint.tx_id
                WHERE tx_outer.address = ? AND ma_tx_mint.quantity > 0
            ),
            current_holdings AS (
                SELECT
                    encode(multi_asset.policy, 'hex') AS policy_id,
                    encode(multi_asset.name, 'hex')   AS asset_name,
                    SUM(ma_tx_out.quantity)           AS quantity
                FROM multi_asset
                JOIN ma_tx_out ON ma_tx_out.ident = multi_asset.id
                JOIN tx_out tx_outer ON tx_outer.id = ma_tx_out.tx_out_id
                WHERE tx_outer.address = ?
                AND NOT EXISTS (
                    SELECT 1
                    FROM tx_in
                    WHERE tx_in.tx_out_id  = tx_outer.tx_id
                        AND tx_in.tx_out_index = tx_outer.index
                )
                GROUP BY multi_asset.policy, multi_asset.name
            )
            SELECT
                mt.policy_id,
                mt.asset_name,
                ch.quantity,
                (SELECT tm.json 
                    FROM tx_metadata tm 
                    WHERE tm.tx_id = mt.mint_tx_id AND tm.key IN (721,20)
                LIMIT 1) AS metadata_json                
            FROM minted_tokens mt
            JOIN current_holdings ch ON mt.policy_id = ch.policy_id AND mt.asset_name = ch.asset_name
            WHERE EXISTS (
                SELECT 1
                FROM tx_metadata tm
                WHERE tm.tx_id = mt.mint_tx_id AND tm.key IN (721,20)
            )            
        ";

        $total = count(DB::connection('cexplorer')->select($sql, [
            $address,
            $address,            
        ]));

        $sql = "WITH encoded_assets AS (
                SELECT
                    ma.id,
                    encode(ma.policy, 'hex') AS policy_id,
                    encode(ma.name, 'hex')   AS asset_name
                FROM multi_asset ma
            ),
            minted_tokens AS (
                SELECT DISTINCT
                    ea.policy_id,
                    ea.asset_name,                    
                    ma_tx_mint.tx_id AS mint_tx_id
                FROM encoded_assets ea                    
                JOIN ma_tx_mint ON ma_tx_mint.ident = ea.id
                JOIN tx_out tx_outer ON tx_outer.tx_id = ma_tx_mint.tx_id
                WHERE tx_outer.address = ? AND ma_tx_mint.quantity > 0
            ),
            current_holdings AS (
                SELECT
                    encode(multi_asset.policy, 'hex') AS policy_id,
                    encode(multi_asset.name, 'hex')   AS asset_name,
                    SUM(ma_tx_out.quantity)           AS quantity
                FROM multi_asset
                JOIN ma_tx_out ON ma_tx_out.ident = multi_asset.id
                JOIN tx_out tx_outer ON tx_outer.id = ma_tx_out.tx_out_id
                WHERE tx_outer.address = ?
                AND NOT EXISTS (
                    SELECT 1
                    FROM tx_in
                    WHERE tx_in.tx_out_id  = tx_outer.tx_id
                    AND tx_in.tx_out_index = tx_outer.index
                )
                GROUP BY multi_asset.policy, multi_asset.name
            )
            SELECT
                mt.policy_id,
                mt.asset_name,
                ch.quantity,                
                (SELECT tm.json 
                    FROM tx_metadata tm 
                    WHERE tm.tx_id = mt.mint_tx_id AND tm.key IN (721,20)
                LIMIT 1) AS metadata_json
            FROM minted_tokens mt
            JOIN current_holdings ch ON mt.policy_id = ch.policy_id AND mt.asset_name = ch.asset_name
            WHERE EXISTS (
                SELECT 1
                FROM tx_metadata tm
                WHERE tm.tx_id = mt.mint_tx_id AND tm.key IN (721,20)
            )
            LIMIT ? OFFSET ?
        ";       
     
        $assets = collect(DB::connection('cexplorer')->select($sql, [
            $address,
            $address,
            $perPage,
            $offset
        ]));

        $id = 0;
        
        foreach($assets as $asset) {
            if ($asset->quantity) {
                $img = null;

                $asset->id = $id;

                $id += 1;

                $asset->decimals = 0;

                $assetHex = $asset->asset_name;
                $policyId = $asset->policy_id;
                $metaData = $asset->metadata_json;

                $cacheKey = 'jsonMetaMint_' . $assetHex.$policyId;

                $jsonMeta = Cache::remember($cacheKey, 3600, function () use ($assetHex, $policyId, $metaData) {
                    return CardanoFingerprint::getTokenJson($assetHex, $policyId, $metaData);
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

                if (strtoupper($asset->asset_name) !== 'ADA') {                    
                    $asset->asset_hex = $asset->asset_name;

                    // Check for CIP-68 asset names...

                    $is_CIP68 = false;
                    $is_NFTTK = false;
                    
                    if (str_starts_with($asset->asset_hex, '0014df10')) {
                        $asset->asset_name = hex2bin(substr($asset->asset_hex, 8));
                        $is_CIP68 = true;                        
                    } else if (str_starts_with($asset->asset_hex, '000643b0')) {
                        $asset->asset_name = hex2bin(substr($asset->asset_hex, 8));
                        $is_CIP68 = true;
                        $is_NFTTK = true;
                    } else {
                        $asset->asset_name = hex2bin($asset->asset_hex);
                    }
                    
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
                        if (empty($img)) { 
                            $assetHex = $asset->asset_hex;
                            $policyId = $asset->policy_id;

                            $cacheKey = 'img_' . $assetHex.$policyId;

                            $img = Cache::remember($cacheKey, 3600, function () use ($assetHex, $policyId) {
                                return CardanoFingerprint::getTokenLogo($assetHex, $policyId);
                            });
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

                if (empty($asset->logo_url)) {
                    $asset->logo_url = $img;
                }
            }
        }

        $assetList = [];

        foreach ($assets as $asset) {
            unset($asset->metadata_json);

            if ($asset->quantity > 0) {
                $assetList[] = $asset;
            }
        }
        
        $paginator = new LengthAwarePaginator($assetList, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query()
        ]);

        // dd($paginator->toArray());
        
        return Inertia::render('mints/Mints', [
            'mints' => $paginator->toArray(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('mints/Create', [
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([            
            'name'       => ['required', 'string', 'max:32'],
            'ticker'     => ['required', 'string', 'max:8'],
            'category'   => ['required', 'string', 'max:32'],
            'link'       => ['required', 'url'],
            'decimals'   => ['required', 'in:0,1,2,3,4,5,6'],
            'number'     => ['required', 'numeric', 'min:1'],
            'short_description' => ['required', 'string', 'max:32'],
        ]);
        
        $metaData = new CreateNftMetadata();

        $policy_id = auth()->user()->wallet->policy_id;

        $additional_info = !empty($request->input('additional_info')) ? $request->input('additional_info') : '';

        $nftMetaData = $metaData->generateNftMetadata($policy_id, $validated, $additional_info);

        $txPrefix = '/var/www/stube/storage/app/private/transactions/'.bin2hex(openssl_random_pseudo_bytes(4)).'/';
      
        $err = [];
        
        $err['response'] = 'error';
        $err['message'] = '';
        
        $spentFee = 0;
        $netwkFee = 0;

        $address = auth()->user()->wallet->address;
        
        $change = $address;

        $data = [];

        $data['destination']  = $change;
        $data['policy_id']    = $policy_id;
        $data['asset_name']   = $validated['name'];
        $data['asset_hex']    = bin2hex($validated['name']);
        $data['token_number'] = $validated['number'];

        $asset = $data;

        $dat = [];

        $dat[] = $data;

        if ($this->make_dir($txPrefix)) {
            $err = app(MintTransactionController::class)->generate_transaction($dat, $change, $txPrefix, $nftMetaData, $spentFee, $netwkFee);
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

        if (!empty($err['response']) && ($err['response'] == 'success')) {
            $transaction = [];
            $parsedvalue = [];

            $transaction['tx_fee']    = $spentFee;
            $transaction['tx_net']    = $netwkFee;
            $transaction['tx_prefix'] = $txPrefix;
            
            $transaction['tx_rate']   = !empty($asset['rate']) ? $asset['rate'] : null;

            if ($inputOutputs = InputOutput::where('user_id', auth()->id())->where('change', $change)->orderby('created_at', 'desc')->first()) {            
                $unparsedvalue = explode('--tx-out ', $inputOutputs->outputs);

                foreach($unparsedvalue as $val) {
                    if (!empty($val)) {
                        $parsedvalue[] = $val;
                    }
                }

                $parsed = [];

                foreach ($parsedvalue as $entry) {

                    if (!str_starts_with($entry, '--mint')) {                                                            
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

                        if ($address === auth()->user()->wallet->address) {
                            // $address = 'self';
                            $address = $address.' (self)';
                        }

                        $tokens = $this->array_unique_multidimensional($tokens);

                        // dd($tokens);

                        $parsed[] = [
                            'address'  => $address,
                            'ada'      => $lovelace / 1_000_000,
                            'tokens'   => $tokens,                        
                        ];
                    }
                }                
            }

            return Inertia::render('assets/Confirm', [
                'transaction' => $transaction,                
                'utxos'       => $parsed
            ]);
        }

        return redirect('mints')->with('error', __('validation.not_implemented_yet'));
        
    }

    private function array_unique_multidimensional(array $array): array 
    {
        $serialized = array_map('serialize', $array);

        $unique = array_unique($serialized);

        return array_map('unserialize', $unique);
    }

    /**
     * Display the specified resource.
     */
    public function show(Mint $mint)
    {
        //
    }

    public function append(Request $request)
    {    
        // dd($request->all());

        $validated = $request->validate([
            'selected_assets.*.policy_id' => 'required|string',
            'selected_assets.*.asset_name' => 'required|string',            
            'selected_assets.*.asset_hex' => 'required|string',
            'selected_assets.*.fingerprint' => 'required|string',
            'selected_assets.*.ticker' => 'required|string',
            'selected_assets.*.description' => 'nullable|string',
            'selected_assets.*.quantity' => 'required|numeric|min:1',
            'selected_assets.*.decimals' => 'required|integer|min:0',
            'selected_assets.*.logo_url' => 'nullable|string',
        ]);

        $newMint = $validated['selected_assets'][0];

        $newMint['present'] = $newMint['quantity'];

        // dd($netMint);
        
        /*
        $newMint = [
            'asset_name'  => $validated('asset_name'),
            'asset_hex'   => $validated('asset_hex'),
            'policy_id'   => $validated('policy_id'),
            'fingerprint' => $validated('fingerprint'),
            'present'     => $validated('quantity'),
            'ticker'      => $validated('ticker'),    
            'logo_url'    => $validated('logo_url'),
            'decimals'    => $validated('decimals')
        ];
        */

        return Inertia::render('mints/Edit', [
            'mint' => $newMint
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Mint $mint)
    {
        //
    }

    public function mint(Request $request)
    {
        $validated = $request->validate([            
            'number' => ['required', 'numeric', 'min:1']
        ]);

        $policy_id = auth()->user()->wallet->policy_id;

        $txPrefix = '/var/www/stube/storage/app/private/transactions/'.bin2hex(openssl_random_pseudo_bytes(4)).'/';
      
        $err = [];
        
        $err['response'] = 'error';
        $err['message'] = '';
        
        $spentFee = 0;
        $netwkFee = 0;

        $address = auth()->user()->wallet->address;
        
        $change = $address;

        $data = [];

        $data['destination']  = $change;
        $data['policy_id']    = $policy_id;
        $data['asset_name']   = $request->input('asset_name');
        $data['asset_hex']    = $request->input('asset_hex');
        $data['token_number'] = intval($validated['number']);

        if (TxOut::hasToken($change, $policy_id, $request->input('asset_hex'))) {
            $asset = $data;

            $dat = [$data];

            if ($this->make_dir($txPrefix)) {
                $err = app(MintTransactionController::class)->generate_transaction($dat, $change, $txPrefix, '', $spentFee, $netwkFee);
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

            if (!empty($err['response']) && ($err['response'] == 'success')) {
                $transaction = [];
                $parsedvalue = [];

                $transaction['tx_fee']    = $spentFee;
                $transaction['tx_net']    = $netwkFee;
                $transaction['tx_prefix'] = $txPrefix;
            
                $transaction['tx_rate']   = !empty($asset['rate']) ? $asset['rate'] : null;

                if ($inputOutputs = InputOutput::where('user_id', auth()->id())->where('change', $change)->orderby('created_at', 'desc')->first()) {            
                    $unparsedvalue = explode('--tx-out ', $inputOutputs->outputs);

                    foreach($unparsedvalue as $val) {
                        if (!empty($val)) {
                            $parsedvalue[] = $val;
                        }
                    }

                    $parsed = [];

                    foreach ($parsedvalue as $entry) {

                        if (!str_starts_with($entry, '--mint')) {                                          
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

                            if ($address === auth()->user()->wallet->address) {
                                // $address = 'self';
                                $address = $address.' (self)';
                            }

                            $tokens = $this->array_unique_multidimensional($tokens);

                            $parsed[] = [
                                'address'  => $address,
                                'ada'      => $lovelace / 1_000_000,
                                'tokens'   => $tokens,                        
                            ];
                        }
                    }
                }                
            
                return Inertia::render('assets/Confirm', [
                    'transaction' => $transaction,                
                    'utxos'       => $parsed
                ]);
            }
        
        } else {
            $err['message'] = 'token_not_present';
        }

        return redirect('mints')->with($err['response'], __('validation.'.$err['message']));
    }

    public function burn(Request $request)
    {
        $showVal = bcdiv($request->input('present'), bcpow("10", (string) $request->input('decimals')), $request->input('decimals'));
        
        $validator = Validator::make($request->all(), [
            'present' => ['required', 'numeric'],
            'number' => ['required', 'numeric', 'lte:present'],
        ],
        [
            'number.lte' => __('validation.number_must_be_less_than').number_format($showVal, 6, ',', '.'),
        ]);

        if ($validator->fails()) {
              return Inertia::render('mints/Burn', [
                'mint' => $request->all(),
                'errors' => $validator->errors(), 
            ]);       
        }

        $validated = $validator->validated();

        $policy_id = auth()->user()->wallet->policy_id;

        $txPrefix = '/var/www/stube/storage/app/private/transactions/'.bin2hex(openssl_random_pseudo_bytes(4)).'/';
      
        $err = [];
        
        $err['response'] = 'error';
        $err['message'] = '';
        
        $spentFee = 0;
        $netwkFee = 0;

        $address = auth()->user()->wallet->address;
        
        $change = $address;

        $data = [];

        $data['destination']  = $change;
        $data['policy_id']    = $policy_id;
        $data['asset_name']   = $request->input('name');
        $data['asset_hex']    = $request->input('asset_hex');
        $data['token_number'] = -intval($validated['number']);
        
        if (TxOut::hasToken($change, $policy_id, $request->input('asset_hex'))) {
            $asset = $data;

            $dat = [$data];

            if ($this->make_dir($txPrefix)) {
                $err = app(MintTransactionController::class)->generate_transaction($dat, $change, $txPrefix, '', $spentFee, $netwkFee);
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
                
            if (!empty($err['response']) && ($err['response'] == 'success')) {
                $transaction = [];
                $parsedvalue = [];

                $transaction['tx_fee']    = $spentFee;
                $transaction['tx_net']    = $netwkFee;
                $transaction['tx_prefix'] = $txPrefix;
            
                $transaction['tx_rate']   = !empty($asset['rate']) ? $asset['rate'] : null;

                if ($inputOutputs = InputOutput::where('user_id', auth()->id())->where('change', $change)->orderby('created_at', 'desc')->first()) {            
                    $unparsedvalue = explode('--tx-out ', $inputOutputs->outputs);

                    foreach($unparsedvalue as $val) {
                        if (!empty($val)) {
                            $parsedvalue[] = $val;
                        }
                    }

                    $parsed = [];

                    foreach ($parsedvalue as $entry) {

                        if (!str_starts_with($entry, '--mint')) {
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

                            if ($address === auth()->user()->wallet->address) {
                                // $address = 'self';
                                $address = $address.' (self)';
                            }

                            $tokens = $this->array_unique_multidimensional($tokens);

                            $parsed[] = [
                                'address'  => $address,
                                'ada'      => $lovelace / 1_000_000,
                                'tokens'   => $tokens,                        
                            ];
                        }
                    }
                }

                return Inertia::render('assets/Confirm', [
                    'transaction' => $transaction,                
                    'utxos'       => $parsed
                ]);
            }
        
        } else {
            $err['message'] = 'token_not_present';
        }

        return redirect('mints')->with($err['response'], __('validation.'.$err['message']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {    
         $newMint = [
            'id'          => 1,
            'asset_name'  => $request->input('asset_name'),
            'asset_hex'   => $request->input('asset_hex'),
            'policy_id'   => $request->input('policy_id'),
            'fingerprint' => $request->input('fingerprint'),
            'present'     => $request->input('quantity'),
            'ticker'      => $request->input('ticker'),    
            'logo_url'    => $request->input('logo_url'),
            'decimals'    => $request->input('decimals'),
            'original'    => $request->input('decimals')
        ];

        return Inertia::render('mints/Burn', [
            'mint' => $newMint
        ]);
    }

    public function search(Request $request)
    {        
        $q = $request->input('q', '');
        
        return response()->json();
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
