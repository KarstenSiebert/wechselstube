<?php

namespace App\Http\Controllers;

use Auth;
use Exception;
use RuntimeException;
use App\Models\InputOutput;
use Illuminate\Http\Request;
use App\Helpers\CardanoCliWrapper;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
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
    
    public function generate_transaction(array $txOutList, string $address, string $txPrefix, string $nftMetaData, float &$fee, float &$net): ?array
    {   
        $err  = [];      
        $user = Auth::user();

        $err['response'] = 'error';
        $err['message'] = '';
    
        $txout = [];
        $txins = [];
            
        $this->cli = new CardanoCliWrapper($txPrefix, '/usr/local/bin/cardano-cli', '/tmp/node.socket');

        if (!file_exists($this->protocolFile)) {
            try {
                $this->cli->queryProtocolParams($this->protocolFile);

            } catch (RuntimeException $e) {
                // dd($e->getMessage());
            }
        }
        
        // Add token to the same destination.

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
            
        // All outputs to forgein wallets, ADA and tokens

        // dd($txout);

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
            
        $tokenList = DB::connection('cexplorer')->select($sql, [$address]);

        foreach ($tokenList as $list) {
            $txins[] = "--tx-in ".substr($list->tx_hash, 2).'#'.$list->index;
        }
            
        // All txins of user address

        // dd($txins);

        // dd($txOutList);

        try {
            $transactionFee = $this->cli->buildTransaction($txins, $txout, $address, 'matx.raw', 0, $nftMetaData);
                        
            // dd($transactionFee);

            // Limit transaction fees to 400000 Lovelace, currently hard coded. Ths moves to the new screen

            if (file_exists($this->cli->txPrefix.'matx.raw') && !empty($transactionFee) && (intval($transactionFee) < 400000)) {
                $this->cli->signTransaction($user->wallet->signing_key);

                if (file_exists($this->cli->txPrefix.'matx.signed')) {
                    
                    InputOutput::Create([
                        'user_id'   => $user->id,
                        'wallet_id' => $user->wallet->id,
                        'inputs'    => implode(' ', $txins),
                        'outputs'   => implode(' ', $txout),
                        'change'    => $address
                    ]);

                    $fee = 0;
                    $net = $transactionFee / 1000000;

                    $err['response'] = 'success';
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
