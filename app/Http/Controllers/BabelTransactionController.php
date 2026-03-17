<?php

namespace App\Http\Controllers;

use Auth;
use Exception;
use App\Models\User;
use RuntimeException;
use App\Models\Wallet;
use App\Models\InputOutput;
use Illuminate\Http\Request;
use App\Helpers\CardanoCliWrapper;
use Illuminate\Support\Facades\DB;

class BabelTransactionController extends Controller
{
    private CardanoCliWrapper $cli;

    private string $protocolFile;

    public function __construct()
    {
        $this->protocolFile = '/var/www/stube/storage/app/private/transactions/protocol.json';
    }

    private function groupTotals(array $data): array
    {
        $result = [];
        
        foreach ($data as $row) {
            $key = $row['destination'] . '-' . $row['policy_id'] . '-' . $row['asset_name'];

            if (!isset($result[$key])) {
                $result[$key] = [
                    'destination'  => $row['destination'],
                    'policy_id'    => $row['policy_id'],
                    'asset_name'   => $row['asset_name'],
                    'asset_hex'    => $row['asset_hex'],
                    'token_number' => 0
                ];
            }

            $result[$key]['token_number'] += $row['token_number'];
        }
    
        return array_values($result);
    }

    private function checkAssetExists($array, $string) 
    {
        list($policy_id, $asset_name) = explode('.', $string);

        $matches = array_filter($array, function($item) use ($policy_id, $asset_name) {
            return $item['policy_id'] === $policy_id && $item['asset_name'] === $asset_name;
        });

        return !empty($matches);
    }

    public function generate_transaction(array $txOutList, string $source, string $service, string $policyId, string $assetHex, string $rate, string $decimals, string $txPrefix, string $nftMetaData, float &$fee, float &$net, $transferFee): ?array
    {   
        $err  = [];        
        $user = Auth::user();

        $err['response'] = 'error';
        $err['message']  = '';
        $err['id']       = '';
        
        $txout = [];
        $txins = [];

        $tokenlists = [];

        $pairs = [];
        
        $sourceLovelace = 0;

        // dd($txOutList);
      
        $this->cli = new CardanoCliWrapper($txPrefix, '/usr/local/bin/cardano-cli', '/tmp/node.socket');

        if (!file_exists($this->protocolFile)) {
            try {
                $this->cli->queryProtocolParams($this->protocolFile);

            } catch (RuntimeException $e) {
                // dd($e->getMessage());
            }
        }

        $txOutList = $this->groupTotals($txOutList);
        
        $chain = [];
        $adach = [];

        foreach ($txOutList as $txo) {
            if (strtoupper($txo['asset_name']) !== 'ADA') {
                $chain[] = explode(',', trim($txo['destination']).', 0 ,'.$txo['token_number'].','.$txo['policy_id'].'.'.$txo['asset_hex']);
            } else {                
                $chain[] = explode(',', trim($txo['destination']).','.$txo['token_number'].', 0 ,'.'ADA');
            }
        }

        // dd($chain);

        $combinedTxout = [];         

        foreach ($chain as [$addr, $lovelace, $value, $string]) {
                
            if (!isset($combinedTxout[$addr])) {
                if (!strcmp($string, 'ADA')) {
                    $combinedTxout[$addr] = $lovelace.'';
                } else {
                    $combinedTxout[$addr] = '0+"'.$value.' '.$string.'"';
                }
            } else {
                if (!strcmp($string, 'ADA')) {
                    $combinedTxout[$addr] .= '+"'.$value.'"';
                } else {
                    $combinedTxout[$addr] .= '+"'.$value.' '.$string.'"';
                }
            }
        }

        $sum = 0;

        foreach ($combinedTxout as $addr => $combined) {
            $strm = $addr.'+'.$combined;

            $parts = explode('+', $combined);

            $lovelace = (int)$parts[0];

            $trxtail = substr($combined, strlen($parts[0]));

            // dd($trxtail);           

            try {
                $mval = $this->cli->calculateMinRequiredUtxo($strm, $this->protocolFile);
                
                $sum += $mval;

                if ($lovelace > $mval) {
                    $mval = $lovelace;                    
                }
                
                $txout[] = '--tx-out '.$addr.'+'.$mval.$trxtail;
                    
            } catch (RuntimeException $e) {
                // dd($e->getMessage());
            }
        }

        $sql = "SELECT
                    tx.hash::text AS tx_hash,
                    tx_outer.index
                FROM tx_out AS tx_outer
                JOIN tx ON tx.id = tx_outer.tx_id
                WHERE tx_outer.address = ?
                AND NOT EXISTS (
                    SELECT 1
                    FROM tx_out t2
                    INNER JOIN tx_in ti ON t2.tx_id = ti.tx_out_id AND t2.index = ti.tx_out_index
                    WHERE t2.id = tx_outer.id)
        ";
            
        $tokenList = DB::connection('cexplorer')->select($sql, [$source]);
        
        foreach ($tokenList as $list) {
            $txins[] = "--tx-in ".substr($list->tx_hash, 2).'#'.$list->index;
            $pairs[] = "('{$list->tx_hash}', '{$list->index}')";
        }
            
        $pairList = implode(", ", $pairs);
        
        if (!empty($pairs) && !empty($pairList)) {
      
            $sql = "SELECT
                    tx.hash::text AS tx_hash,
                    tx_outer.index,
                    tx_outer.address,
                    tx_outer.value,
                    ma.policy::text AS policy_id,
                    ma.name::text AS asset_name,
                    ma_tx_out.quantity
                FROM tx_out AS tx_outer
                LEFT JOIN ma_tx_out ON tx_outer.id = ma_tx_out.tx_out_id
                LEFT JOIN multi_asset ma ON ma.id = ma_tx_out.ident                
                JOIN tx ON tx.id = tx_outer.tx_id                
                WHERE tx_outer.address = ?
                AND (tx.hash::text, tx_outer.index) IN ($pairList)
                AND NOT EXISTS (
                    SELECT 1
                    FROM tx_out t2
                    INNER JOIN tx_in ti ON t2.tx_id = ti.tx_out_id AND t2.index = ti.tx_out_index
                    WHERE t2.id = tx_outer.id)       
            ";

            $tokenList = DB::connection('cexplorer')->select($sql, [$source]);

        } else {
            $tokenList = [];
        }
                       
        if (!empty($tokenList)) {
            $sourceLovelace = collect($tokenList)->unique(fn($tx) => $tx->tx_hash . '-' . $tx->index)->sum(fn($tx) => (int) $tx->value);
        
        } else {
            $sourceLovelace = 0;

            $err['message'] = 'sourceLovelace is 0';

            return $err;
        }

        // dd($sourceLovelace, $tokenList, $txOutList);       
        
        $aggregated = [];

        foreach ($tokenList as $list) {    
            $policy = bin2hex($list->policy_id);
            $asset  = bin2hex($list->asset_name);
            $key    = $policy . '_' . $asset;

            if (!isset($aggregated[$key])) {
        
                $aggregated[$key] = [
                    'policy_id'  => $list->policy_id,
                    'asset_name' => $list->asset_name,
                    'quantity'   => 0,
                ];
            }

            $aggregated[$key]['quantity'] += (int) $list->quantity;
        }

        $aggregated = array_values($aggregated);

        $tempList = [];

        // dd($aggregated);

        if (!$this->checkAssetExists($aggregated, '\x'.$policyId.'.\x'.$assetHex)) {
            $err['message'] = 'Token not in assets';
            return $err;
        }        

        foreach($aggregated as $token) {

            foreach($txOutList as $list) {
                if ($list['asset_hex'] == substr($token['asset_name'], 2)) {
                    $token['quantity'] -= $list['token_number'];
                }
            }            

            $tempList[] = $token;
        }
        
        $tout = '';

        $sourceTokenVal = 0;

        foreach($tempList as $token) {
                        
            if ((substr($token['policy_id'], 2) == $policyId) && (substr($token['asset_name'], 2) == $assetHex)) {
                $sourceTokenVal += $token['quantity'];

            } else {
                if ($token['quantity'] > 0) {
                    $tout .= '+"'.$token['quantity'].' '.substr($token['policy_id'], 2).'.'.substr($token['asset_name'], 2).'"'; 
                }
            }
        }
       
        $babelfeeTokens = (int) floor($this->calculateTokenFee($sum + $transferFee, $decimals, $rate));

        $babelfee = bcdiv($babelfeeTokens, bcpow("10", (string) $decimals), $decimals);

        // dd($decimals, $sourceTokenVal, $babelfee, $babelfeeTokens);

        $sourceTokenVal -= $babelfeeTokens;

        if ($sourceTokenVal < 0) {
            $err['message'] = 'Not enough tokens, missing: '. abs($sourceTokenVal);
            return $err;
        }

        if ($sourceTokenVal > 0) {
            $tout .= '+"'.$sourceTokenVal.' '.$policyId.'.'.$assetHex.'"';
        }
             
        $txou = $source.'+0'.$tout;

        try {
            $mval = $this->cli->calculateMinRequiredUtxo($txou, $this->protocolFile);

            if ($mval < $sourceLovelace) {
                $mval = $sourceLovelace;
            }

            $txout[] = '--tx-out '.$source.'+'.$mval.''.$tout;
                    
        } catch (RuntimeException $e) {
            dd("CLI Error: " . $e->getMessage());
        }

        $sql = "SELECT 
                    tx.hash::text AS tx_hash,
                    tx_outer.index,
                    tx_outer.address,
                    tx_outer.value,
                    ma.policy::text AS policy_id,
                    ma.name::text AS asset_name,
                    ma_tx_out.quantity
                FROM tx_out AS tx_outer
                LEFT JOIN ma_tx_out ON tx_outer.id = ma_tx_out.tx_out_id
                LEFT JOIN multi_asset ma ON ma.id = ma_tx_out.ident
                JOIN tx ON tx.id = tx_outer.tx_id
                WHERE tx_outer.address = ?
                AND NOT EXISTS (
                    SELECT 1
                    FROM tx_out t2
                    INNER JOIN tx_in ti ON t2.tx_id = ti.tx_out_id AND t2.index = ti.tx_out_index
                    WHERE t2.id = tx_outer.id)       
        ";

        $tokenList = DB::connection('cexplorer')->select($sql, [$service]);
               
        foreach($tokenList as $token) {                
            $txins[] = '--tx-in '.substr($token->tx_hash, 2).'#'.$token->index;
        }
        
        $txins = array_unique($txins);            
        
        // Filter all txout for service, as those go to change anyway
        
        $txout = array_values(array_filter($txout, fn($v) => !str_contains($v, $service)));

        // dd($service, $txins, $txout, "STOP");
        
        try {
            $transactionFee = $this->cli->buildTransaction($txins, $txout, $service, 'matx.raw', $sourceLovelace, $nftMetaData);
            
            // dd($transactionFee, $service);

            // Limit transaction fees to 400000 Lovelace, currently hard coded. Ths moves to the new screen

            if (file_exists($this->cli->txPrefix.'matx.raw') && !empty($transactionFee) && (intval($transactionFee) < 400000)) {
                
                if ($provider = Wallet::where('address', $service)->first()) {
                    $this->cli->witnessTransaction($provider->signing_key, 'provider');
                
                    if (file_exists($this->cli->txPrefix.'witness.provider') ) {

                        if (!empty($user)) {
                            $this->cli->witnessTransaction($user->wallet->signing_key, 'user');
                        
                        } else {
                            $user = User::with('wallet')
                                ->whereHas('wallet', function ($query) use ($source) {
                                        $query->where('address', $source);
                                })
                            ->first();

                            $this->cli->witnessTransaction($user->wallet->signing_key, 'user');
                        }

                        if (file_exists($this->cli->txPrefix.'witness.user')) {                            
                            $this->cli->assembleSignature();

                            if (file_exists($this->cli->txPrefix.'matx.signed')) {
                                
                                InputOutput::Create([
                                    'user_id'   => $user->id,
                                    'wallet_id' => $user->wallet->id,
                                    'inputs'    => implode(' ', $txins),
                                    'outputs'   => implode(' ', $txout),
                                    'change'    => $service
                                ]);

                                $fee = $babelfee;
                                $net = $transactionFee / 1000000;

                                $err['response'] = 'success';
                            }
                        }
                    }
                }
            }

        } catch (RuntimeException $e) {
            // dd($e->getMessage());
            
            if (preg_match('/Error:\s*(.+?\.)/s', $e->getMessage(), $matches)) {
                $err['message'] = $matches[1];
            } else {
                $err['message'] = 'Error in assembling.';
            }            
        }

        return $err;
    }

    private function calculateTokenFee(string $feeLovelace, int $tokenDecimals, string $tokenRate): string 
    {
        $lovelacePerAda = bcpow("10", "6");
    
        $feeAda = bcdiv($feeLovelace, $lovelacePerAda, 6);
    
        $neededTokens = bcmul($feeAda, $tokenRate, 6);

        $neededOnChain = bcmul($neededTokens, bcpow("10", (string) $tokenDecimals));

        return $neededOnChain;
    }

    public function build_raw_transaction(array $txOutList, string $source, string $service, string $policyId, string $assetHex, string $rate, string $decimals, string $txPrefix, string $nftMetaData, float &$fee, float &$net, $transferFee): ?array
    {   
        $err  = [];
        
        $err['response'] = 'error';
        $err['message']  = '';
        $err['id']       = '';

        $txout = [];
        $txins = [];

        $tokenlists = [];

        $pairs = [];
        
        $sourceLovelace = 0;

        // dd($txOutList);        
      
        $this->cli = new CardanoCliWrapper($txPrefix, '/usr/local/bin/cardano-cli', '/tmp/node.socket');

        if (!file_exists($this->protocolFile)) {
            try {
                $this->cli->queryProtocolParams($this->protocolFile);

            } catch (RuntimeException $e) {
                // dd($e->getMessage());
            }
        }

        $txOutList = $this->groupTotals($txOutList);
        
        $chain = [];
        $adach = [];

        foreach ($txOutList as $txo) {
            if (strtoupper($txo['asset_name']) !== 'ADA') {
                $chain[] = explode(',', trim($txo['destination']).', 0 ,'.$txo['token_number'].','.$txo['policy_id'].'.'.$txo['asset_hex']);
            } else {                
                $chain[] = explode(',', trim($txo['destination']).','.$txo['token_number'].', 0 ,'.'ADA');
            }
        }

        // dd($chain);

        $combinedTxout = [];         

        foreach ($chain as [$addr, $lovelace, $value, $string]) {
                
            if (!isset($combinedTxout[$addr])) {
                if (!strcmp($string, 'ADA')) {
                    $combinedTxout[$addr] = $lovelace.'';
                } else {
                    $combinedTxout[$addr] = '0+"'.$value.' '.$string.'"';
                }
            } else {
                if (!strcmp($string, 'ADA')) {
                    $combinedTxout[$addr] .= '+"'.$value.'"';
                } else {
                    $combinedTxout[$addr] .= '+"'.$value.' '.$string.'"';
                }
            }
        }

        $sum = 0;

        foreach ($combinedTxout as $addr => $combined) {
            $strm = $addr.'+'.$combined;

            $parts = explode('+', $combined);

            $lovelace = (int)$parts[0];

            $trxtail = substr($combined, strlen($parts[0]));

            // dd($trxtail);           

            try {
                $mval = $this->cli->calculateMinRequiredUtxo($strm, $this->protocolFile);
                
                $sum += $mval;

                if ($lovelace > $mval) {
                    $mval = $lovelace;                    
                }
                
                $txout[] = '--tx-out '.$addr.'+'.$mval.$trxtail;
                    
            } catch (RuntimeException $e) {
                // dd($e->getMessage());
            }
        }

        $sql = "SELECT
                    tx.hash::text AS tx_hash,
                    tx_outer.index
                FROM tx_out AS tx_outer
                JOIN tx ON tx.id = tx_outer.tx_id
                WHERE tx_outer.address = ?
                AND NOT EXISTS (
                    SELECT 1
                    FROM tx_out t2
                    INNER JOIN tx_in ti ON t2.tx_id = ti.tx_out_id AND t2.index = ti.tx_out_index
                    WHERE t2.id = tx_outer.id)
        ";
            
        $tokenList = DB::connection('cexplorer')->select($sql, [$source]);
        
        foreach ($tokenList as $list) {
            $txins[] = "--tx-in ".substr($list->tx_hash, 2).'#'.$list->index;
            $pairs[] = "('{$list->tx_hash}', '{$list->index}')";
        }
            
        $pairList = implode(", ", $pairs);
        
        $sql = "SELECT
                    tx.hash::text AS tx_hash,
                    tx_outer.index,
                    tx_outer.address,
                    tx_outer.value,
                    ma.policy::text AS policy_id,
                    ma.name::text AS asset_name,
                    ma_tx_out.quantity
                FROM tx_out AS tx_outer
                LEFT JOIN ma_tx_out ON tx_outer.id = ma_tx_out.tx_out_id
                LEFT JOIN multi_asset ma ON ma.id = ma_tx_out.ident                
                JOIN tx ON tx.id = tx_outer.tx_id                
                WHERE tx_outer.address = ?
                AND (tx.hash::text, tx_outer.index) IN ($pairList)
                AND NOT EXISTS (
                    SELECT 1
                    FROM tx_out t2
                    INNER JOIN tx_in ti ON t2.tx_id = ti.tx_out_id AND t2.index = ti.tx_out_index
                    WHERE t2.id = tx_outer.id)       
        ";

        $tokenList = DB::connection('cexplorer')->select($sql, [$source]);
                       
        $sourceLovelace = collect($tokenList)->unique(fn($tx) => $tx->tx_hash . '-' . $tx->index)->sum(fn($tx) => (int) $tx->value);

        // dd($sourceLovelace, $tokenList, $txOutList);       
        
        $aggregated = [];

        foreach ($tokenList as $list) {    
            $policy = bin2hex($list->policy_id);
            $asset  = bin2hex($list->asset_name);
            $key    = $policy . '_' . $asset;

            if (!isset($aggregated[$key])) {
        
                $aggregated[$key] = [
                    'policy_id'  => $list->policy_id,
                    'asset_name' => $list->asset_name,
                    'quantity'   => 0,
                ];
            }

            $aggregated[$key]['quantity'] += (int) $list->quantity;
        }

        $aggregated = array_values($aggregated);

        $tempList = [];

        // dd($aggregated);

        if (!$this->checkAssetExists($aggregated, '\x'.$policyId.'.\x'.$assetHex)) {
            $err['message'] = 'Token not in assets';            
            return $err;
        }

        // dd($aggregated);

        foreach($aggregated as $token) {

            foreach($txOutList as $list) {
                if ($list['asset_hex'] == substr($token['asset_name'], 2)) {
                    $token['quantity'] -= $list['token_number'];
                }
            }            

            $tempList[] = $token;
        }
        
        $tout = '';

        $sourceTokenVal = 0;

        foreach($tempList as $token) {
                        
            if ((substr($token['policy_id'], 2) == $policyId) && (substr($token['asset_name'], 2) == $assetHex)) {
                $sourceTokenVal += $token['quantity'];

            } else {
                if ($token['quantity'] > 0) {
                    $tout .= '+"'.$token['quantity'].' '.substr($token['policy_id'], 2).'.'.substr($token['asset_name'], 2).'"'; 
                }
            }
        }
       
        $babelfeeTokens = (int) floor($this->calculateTokenFee($sum + $transferFee, $decimals, $rate));

        $babelfee = bcdiv($babelfeeTokens, bcpow("10", (string) $decimals), $decimals);

        // dd($decimals, $sourceTokenVal, $babelfee, $babelfeeTokens);

        $sourceTokenVal -= $babelfeeTokens;

        if ($sourceTokenVal < 0) {
            $err['message'] = 'Not enough tokens, missing: '. abs($sourceTokenVal);
            return $err;
        }

        if ($sourceTokenVal > 0) {
            $tout .= '+"'.$sourceTokenVal.' '.$policyId.'.'.$assetHex.'"';
        }
             
        $txou = $source.'+0'.$tout;

        try {
            $mval = $this->cli->calculateMinRequiredUtxo($txou, $this->protocolFile);

            if ($mval < $sourceLovelace) {
                $mval = $sourceLovelace;
            }

            $txout[] = '--tx-out '.$source.'+'.$mval.''.$tout;
                    
        } catch (RuntimeException $e) {
            dd("CLI Error: " . $e->getMessage());
        }

        $sql = "SELECT 
                    tx.hash::text AS tx_hash,
                    tx_outer.index,
                    tx_outer.address,
                    tx_outer.value,
                    ma.policy::text AS policy_id,
                    ma.name::text AS asset_name,
                    ma_tx_out.quantity
                FROM tx_out AS tx_outer
                LEFT JOIN ma_tx_out ON tx_outer.id = ma_tx_out.tx_out_id
                LEFT JOIN multi_asset ma ON ma.id = ma_tx_out.ident
                JOIN tx ON tx.id = tx_outer.tx_id
                WHERE tx_outer.address = ?
                AND NOT EXISTS (
                    SELECT 1
                    FROM tx_out t2
                    INNER JOIN tx_in ti ON t2.tx_id = ti.tx_out_id AND t2.index = ti.tx_out_index
                    WHERE t2.id = tx_outer.id)       
        ";

        $tokenList = DB::connection('cexplorer')->select($sql, [$service]);
               
        foreach($tokenList as $token) {                
            $txins[] = '--tx-in '.substr($token->tx_hash, 2).'#'.$token->index;
        }
        
        $txins = array_unique($txins);            
        
        // Filter all txout for service, as those go to change anyway
        
        $txout = array_values(array_filter($txout, fn($v) => !str_contains($v, $service)));

        // dd($service, $txins, $txout, "STOP");
        
        try {
            $transactionFee = $this->cli->buildTransaction($txins, $txout, $service, 'matx.raw', $sourceLovelace, $nftMetaData);
            
            // dd($transactionFee, $service);

            // Limit transaction fees to 400000 Lovelace, currently hard coded. This moves to the new screen

            if (file_exists($this->cli->txPrefix.'matx.raw') && !empty($transactionFee) && (intval($transactionFee) < 400000)) {
            
                if ($provider = Wallet::where('address', $service)->first()) {
                    $this->cli->witnessTransaction($provider->signing_key, 'provider');

                    if (file_exists($this->cli->txPrefix.'witness.provider') ) {
                        
                        $txBody = json_decode(file_get_contents($this->cli->txPrefix.'matx.raw'), true);                        
                        $retVal = $txBody['cborHex'];
                        
                        $err['response'] = 'success';
                        $err['message']  = $retVal;                        
                    }
                }
            }

        } catch (RuntimeException $e) {
            // dd($e->getMessage());
            
            if (preg_match('/Error:\s*(.+?\.)/s', $e->getMessage(), $matches)) {
                $err['message'] = $matches[1];
            } else {
                $err['message'] = 'Error in assembling.';
            }            
        }

        return $err;
    }

    public function build_hydra_transaction(array $txOutList, string $source, string $service, string $policyId, string $assetHex, string $rate, string $decimals, string $txPrefix, string $nftMetaData, float &$fee, float &$net, $transferFee): ?array
    {   
        $err  = [];
        
        $err['response'] = 'error';
        $err['message']  = '';
        $err['id']       = '';

        $txout = [];
        $txins = [];

        $tokenlists = [];

        $pairs = [];
        
        $sourceLovelace = 0;

        // dd($txOutList);        
      
        $this->cli = new CardanoCliWrapper($txPrefix, '/usr/local/bin/cardano-cli', '/tmp/node.socket');

        if (!file_exists($this->protocolFile)) {
            try {
                $this->cli->queryProtocolParams($this->protocolFile);

            } catch (RuntimeException $e) {
                // dd($e->getMessage());
            }
        }

        $txOutList = $this->groupTotals($txOutList);
        
        $chain = [];
        $adach = [];

        foreach ($txOutList as $txo) {
            if (strtoupper($txo['asset_name']) !== 'ADA') {
                $chain[] = explode(',', trim($txo['destination']).', 0 ,'.$txo['token_number'].','.$txo['policy_id'].'.'.$txo['asset_hex']);
            } else {                
                $chain[] = explode(',', trim($txo['destination']).','.$txo['token_number'].', 0 ,'.'ADA');
            }
        }

        // dd($chain);

        $combinedTxout = [];         

        foreach ($chain as [$addr, $lovelace, $value, $string]) {
                
            if (!isset($combinedTxout[$addr])) {
                if (!strcmp($string, 'ADA')) {
                    $combinedTxout[$addr] = $lovelace.'';
                } else {
                    $combinedTxout[$addr] = '0+"'.$value.' '.$string.'"';
                }
            } else {
                if (!strcmp($string, 'ADA')) {
                    $combinedTxout[$addr] .= '+"'.$value.'"';
                } else {
                    $combinedTxout[$addr] .= '+"'.$value.' '.$string.'"';
                }
            }
        }

        $sum = 0;

        $buf = 100000;

        foreach ($combinedTxout as $addr => $combined) {
            $strm = $addr.'+'.$combined;

            $parts = explode('+', $combined);

            $lovelace = (int)$parts[0];

            $trxtail = substr($combined, strlen($parts[0]));

            // dd($trxtail);           

            try {
                $mval = $this->cli->calculateMinRequiredUtxo($strm, $this->protocolFile) + $buf;
                
                $sum += $mval;

                if ($lovelace > $mval) {
                    $mval = $lovelace;                    
                }
                
                $txout[] = '--tx-out '.$addr.'+'.$mval.$trxtail;
                    
            } catch (RuntimeException $e) {
                // dd($e->getMessage());
            }
        }

        $sql = "SELECT
                    tx.hash::text AS tx_hash,
                    tx_outer.index
                FROM tx_out AS tx_outer
                JOIN tx ON tx.id = tx_outer.tx_id
                WHERE tx_outer.address = ?
                AND NOT EXISTS (
                    SELECT 1
                    FROM tx_out t2
                    INNER JOIN tx_in ti ON t2.tx_id = ti.tx_out_id AND t2.index = ti.tx_out_index
                    WHERE t2.id = tx_outer.id)
        ";
            
        $tokenList = DB::connection('cexplorer')->select($sql, [$source]);
        
        foreach ($tokenList as $list) {
            $txins[] = "--tx-in ".substr($list->tx_hash, 2).'#'.$list->index;
            $pairs[] = "('{$list->tx_hash}', '{$list->index}')";
        }
            
        $pairList = implode(", ", $pairs);
        
        $sql = "SELECT
                    tx.hash::text AS tx_hash,
                    tx_outer.index,
                    tx_outer.address,
                    tx_outer.value,
                    ma.policy::text AS policy_id,
                    ma.name::text AS asset_name,
                    ma_tx_out.quantity
                FROM tx_out AS tx_outer
                LEFT JOIN ma_tx_out ON tx_outer.id = ma_tx_out.tx_out_id
                LEFT JOIN multi_asset ma ON ma.id = ma_tx_out.ident                
                JOIN tx ON tx.id = tx_outer.tx_id                
                WHERE tx_outer.address = ?
                AND (tx.hash::text, tx_outer.index) IN ($pairList)
                AND NOT EXISTS (
                    SELECT 1
                    FROM tx_out t2
                    INNER JOIN tx_in ti ON t2.tx_id = ti.tx_out_id AND t2.index = ti.tx_out_index
                    WHERE t2.id = tx_outer.id)       
        ";

        $tokenList = DB::connection('cexplorer')->select($sql, [$source]);
                       
        $sourceLovelace = collect($tokenList)->unique(fn($tx) => $tx->tx_hash . '-' . $tx->index)->sum(fn($tx) => (int) $tx->value);

        // dd($sourceLovelace, $tokenList, $txOutList);       
        
        $aggregated = [];

        foreach ($tokenList as $list) {    
            $policy = bin2hex($list->policy_id);
            $asset  = bin2hex($list->asset_name);
            $key    = $policy . '_' . $asset;

            if (!isset($aggregated[$key])) {
        
                $aggregated[$key] = [
                    'policy_id'  => $list->policy_id,
                    'asset_name' => $list->asset_name,
                    'quantity'   => 0,
                ];
            }

            $aggregated[$key]['quantity'] += (int) $list->quantity;
        }

        $aggregated = array_values($aggregated);

        $tempList = [];

        // dd($aggregated);

        if (!$this->checkAssetExists($aggregated, '\x'.$policyId.'.\x'.$assetHex)) {
            $err['message'] = 'Token not in assets';            
            return $err;
        }

        // dd($aggregated);

        foreach($aggregated as $token) {

            foreach($txOutList as $list) {
                if ($list['asset_hex'] == substr($token['asset_name'], 2)) {
                    $token['quantity'] -= $list['token_number'];
                }
            }            

            $tempList[] = $token;
        }
        
        $tout = '';

        $sourceTokenVal = 0;

        foreach($tempList as $token) {
                        
            if ((substr($token['policy_id'], 2) == $policyId) && (substr($token['asset_name'], 2) == $assetHex)) {
                $sourceTokenVal += $token['quantity'];

            } else {
                if ($token['quantity'] > 0) {
                    $tout .= '+"'.$token['quantity'].' '.substr($token['policy_id'], 2).'.'.substr($token['asset_name'], 2).'"'; 
                }
            }
        }
       
        $babelfeeTokens = (int) floor($this->calculateTokenFee($sum + $transferFee, $decimals, $rate));

        $babelfee = bcdiv($babelfeeTokens, bcpow("10", (string) $decimals), $decimals);

        // dd($decimals, $sourceTokenVal, $babelfee, $babelfeeTokens);

        $sourceTokenVal -= $babelfeeTokens;

        if ($sourceTokenVal < 0) {
            $err['message'] = 'Not enough tokens, missing: '. abs($sourceTokenVal);
            return $err;
        }

        if ($sourceTokenVal > 0) {
            $tout .= '+"'.$sourceTokenVal.' '.$policyId.'.'.$assetHex.'"';
        }
             
        $txou = $source.'+0'.$tout;

        try {
            $mval = $this->cli->calculateMinRequiredUtxo($txou, $this->protocolFile) + $buf;

            if ($mval < $sourceLovelace) {
                $mval = $sourceLovelace;
            }

            $txout[] = '--tx-out '.$source.'+'.$mval.''.$tout;
                    
        } catch (RuntimeException $e) {
            dd("CLI Error: " . $e->getMessage());
        }

        $sql = "SELECT 
                    tx.hash::text AS tx_hash,
                    tx_outer.index,
                    tx_outer.address,
                    tx_outer.value,
                    ma.policy::text AS policy_id,
                    ma.name::text AS asset_name,
                    ma_tx_out.quantity
                FROM tx_out AS tx_outer
                LEFT JOIN ma_tx_out ON tx_outer.id = ma_tx_out.tx_out_id
                LEFT JOIN multi_asset ma ON ma.id = ma_tx_out.ident
                JOIN tx ON tx.id = tx_outer.tx_id
                WHERE tx_outer.address = ?
                AND NOT EXISTS (
                    SELECT 1
                    FROM tx_out t2
                    INNER JOIN tx_in ti ON t2.tx_id = ti.tx_out_id AND t2.index = ti.tx_out_index
                    WHERE t2.id = tx_outer.id)       
        ";

        $tokenList = DB::connection('cexplorer')->select($sql, [$service]);
                     
        $tout = '';

        foreach($tokenList as $token) {
                    
            $tout = '+"'.$token->quantity.' '.substr($token->policy_id, 2).'.'.substr($token->asset_name, 2).'"';

            // dd($tout);

            $txou = $service.'+0'.$tout;

            // dd($txou);

            try {                
                $mval = $this->cli->calculateMinRequiredUtxo($txou, $this->protocolFile) + $buf;
                
                $txout[] = '--tx-out '.$service.'+'.$mval.''.$tout;
                    
            } catch (RuntimeException $e) {
                dd("CLI Error: " . $e->getMessage());
            }                 
                
            $txins[] = '--tx-in '.substr($token->tx_hash, 2).'#'.$token->index;
        }
        
        $txins = array_unique($txins);            
                
        dd($service, $txins, $txout, "STOP");

        $hydraFee = 0;
        
        try {
            $transactionFee = $this->cli->buildHydraTransaction($txins, $txout, 'matx.raw', $hydraFee, $sourceLovelace, $nftMetaData);
            
            // dd($transactionFee, $service);

            // Limit transaction fees to 400000 Lovelace, currently hard coded. This moves to the new screen

            if (file_exists($this->cli->txPrefix.'matx.raw') && !empty($transactionFee) && (intval($transactionFee) < 400000)) {
            
                if ($provider = Wallet::where('address', $service)->first()) {
                    $this->cli->witnessTransaction($provider->signing_key, 'provider');

                    if (file_exists($this->cli->txPrefix.'witness.provider') ) {
                        
                        $txBody = json_decode(file_get_contents($this->cli->txPrefix.'matx.raw'), true);                        
                        $retVal = $txBody['cborHex'];
                        
                        $err['response'] = 'success';
                        $err['message']  = $retVal;                        
                    }
                }
            }

        } catch (RuntimeException $e) {
            // dd($e->getMessage());
            
            if (preg_match('/Error:\s*(.+?\.)/s', $e->getMessage(), $matches)) {
                $err['message'] = $matches[1];
            } else {
                $err['message'] = 'Error in assembling.';
            }            
        }

        return $err;
    }

}
