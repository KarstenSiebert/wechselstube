<?php

namespace App\Http\Controllers\Api;

use DB;
use Auth;
use Hash;
use DateTime;
use DateTimeZone;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Inbound;
use App\Models\BabelFee;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Helpers\ContainerMetadata;
use App\Helpers\CardanoCliWrapper;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\BabelTransactionController;

class ApiTransactionController extends Controller
{
    public function login(Request $request)
    {
        $user = User::where('email', $request->email)->first();
    
        if (empty($user) || !Hash::check($request->password, $user->password)) {
            return response()->json(['post' => 'INVALID_CREDENTIALS'], 401);
        }
        
        if (!empty($request->service) && (strlen($request->service) == 32) && ($inbound = Inbound::where('hash', trim($request->service))->where('is_active', true)->first())) {

            $service = [];
            
            $date = new DateTime('now', new DateTimeZone('UTC'));
            $date->modify('+5 minutes');

            $service['token']       = $user->createToken(trim($request->service))->plainTextToken;
            $service['version']     = 'c402-1.0';
            $service['chain']       = 'Cardano';
            $service['nonce']       = $inbound->hash;
            $service['amount']      = bcdiv($inbound->cost, bcpow("10", (string) $inbound->decimals), $inbound->decimals);            
            $service['currency']    = $inbound->inbound_hex;
            $service['policy']      = $inbound->policy_id;        
            $service['fingerprint'] = $inbound->fingerprint;
            $service['payTo']       = Wallet::where('user_id', $inbound->user_id)->value('address');
            $service['receiver']    = User::where('id', $inbound->user_id)->value('name');
            $service['facilitator'] = "";
            $service['expires']     = $date->format('Y-m-d H:i:s') . ' UTC';
            $service['description'] = $inbound->location;
            
            return response()->json($service, 402)->header('X-Payment-Request', $inbound->hash);
        }
               
        return response()->json(['post' => 'INVALID_SERVICE'], 404);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
    
        return response()->json(['post' => 'SESSION_CLOSE']);
    }

    public function confirm(Request $request)
    {
        $err = [];
        
        $err['response'] = 'error';
        $err['message']  = 'Wrong command';
        $err['id']       = '';

        $hash = $request->header('X-Payment-Confirm', '');
        
        if (empty($hash)) {
            return response()->json(['post' => 'SERVICE_NOT_GRANTED', 'transaction' => $err]);

        } else {
            
            // Check for utxos, f given, we have a remote wallet and will prepare PAYMENT_TO_SIGN

            $txPrefix = '/var/www/stube/storage/app/private/transactions/'.bin2hex(openssl_random_pseudo_bytes(4)).'/';
            
            $err['message'] = '';
        
            $user = Auth::user();

            if (!empty($user)) {
                $address = $user->wallet->address;        
        
            } else {
                $address = ''; // Enter an address for testing
            }

            if (empty($user) && !empty($address)) {
                $user = User::with('wallet')
                        ->whereHas('wallet', function ($query) use ($address) {
                            $query->where('address', $address);
                        })
                        ->first();
            }

            if (!empty($address) && !empty($user) && !empty($hash) && $this->make_dir($txPrefix)) {
            
                if ($inbound = Inbound::where('hash', $hash)->first()) {
                    $transferFee = 200000;

                    $spentFee = 0;
                    $netwkFee = 0;

                    $data = [];
                
                    $data['destination']  = Wallet::where('user_id', $inbound->user_id)->value('address');
                    $data['policy_id']    = $inbound->policy_id;
                    $data['asset_name']   = $inbound->inbound_token;
                    $data['asset_hex']    = $inbound->inbound_hex;
                    $data['token_number'] = $inbound->cost;
              
                    $change = $data['destination'];
            
                    $asset = [$data];

                    // $metaData = app(ContainerMetadata::class)->generateContainerMetadata($hash);

                    $metaData = '';
                
                    $err = app(BabelTransactionController::class)->generate_transaction($asset, $address, $change, $data['policy_id'], $data['asset_hex'], "0", $inbound->decimals, $txPrefix, $metaData, $spentFee, $netwkFee, $transferFee);
                              
                    // dd($err);

                    if (empty($err['response'])) {
                        $err['response'] = 'error';
                    }
                       
                    // $err['response'] = 'error';

                    if ($err['response'] == 'success') {
                        $err['response'] = 'error';
                    
                        $cli = new CardanoCliWrapper($txPrefix, '/usr/local/bin/cardano-cli', '/tmp/node.socket');
      
                        if (file_exists($cli->txPrefix.'matx.signed')) {
                                
                            $retVal = $cli->submitTransaction();

                            if (strlen($retVal) == 64) {

                                Transaction::Create([
                                    'user_id' => $user->id,
                                    'transaction_id' => $retVal,
                                    'transaction_fee' => floatval($netwkFee)
                                ]);
                                                    
                                $err['response'] = 'success';
                                $err['id']       = $retVal;
                            }
                        }
                                    
                        $sercost = bcdiv($inbound->cost, bcpow("10", (string) $inbound->decimals), $inbound->decimals);

                        $date = new DateTime('now', new DateTimeZone('UTC'));

                        $err['message'] = $sercost.' '.$inbound->inbound_token.', '. $date->format('Y-m-d H:i:s').' UTC';
                    }
                }

                $this->remove_dir($txPrefix);
            }            
        }
    
        $err['message'] = ($err['response'] == 'success') ? 'SERVICE_GRANTED' : 'SERVICE_NOT_GRANTED';
        
        return response()->json(['transaction' => $err])->header('X-Payment-Response', 'confirmed;tx='.$err['id']);
    }

    public function create(Request $request)
    {
        $user = User::where('email', $request->email)->first();
    
        if (empty($user) || !Hash::check($request->password, $user->password)) {
            return response()->json(['post' => 'INVALID_CREDENTIALS'], 401);
        }
        
        if (!empty($request->service) && !empty($request->cost) && !empty($request->currency)) {
                
            $inbound = new Inbound();

            $inbound->user_id       = $user->id;
            $inbound->location      = substr(trim($request->service), 0, 255);
            $inbound->inbound_token = substr(trim($request->currency), 0, 32);
            $inbound->hash          = substr(hash('sha256', openssl_random_pseudo_bytes(1024)), 0, 32);
            $inbound->is_active     = true;

            if ($babelfee = BabelFee::where('babelfee_token', $inbound->inbound_token)->where('is_active', true)->first()) {
                    
                $inbound->fingerprint   = $babelfee->fingerprint;
                $inbound->policy_id     = $babelfee->policy_id;                
                $inbound->decimals      = $babelfee->decimals;
                $inbound->inbound_hex   = bin2hex($babelfee->babelfee_token);

                $inbound->cost = intval(bcmul(floatval($request->cost), bcpow("10", (string) $inbound->decimals)));
                    
                if ($babelfee->babelfee_token === 'USDM' || $babelfee->babelfee_token === 'Wechselstuben') {
                    $inbound->inbound_hex = '0014df10'.$inbound->inbound_hex;
                }
                
                if ($inbound->save()) {
                    return response()->json(['post' => 'SERVICE_CREATED', 'service' => $inbound->hash]);
                }
            }

            return response()->json(['post' => 'SERVICE_NOT_CREATED']);
        }
               
        return response()->json(['post' => 'INVALID_SERVICE'], 404);
    }

    public function delete(Request $request)
    {
        $user = User::where('email', $request->email)->first();
    
        if (empty($user) || !Hash::check($request->password, $user->password)) {
            return response()->json(['post' => 'INVALID_CREDENTIALS'], 401);
        }
        
        // Service is the hash value, and the service belongs to that user
    
        if (!empty($request->service) && (strlen(trim($request->service)) == 32) && ($inbound = Inbound::where('hash', trim($request->service))->where('user_id', $user->id)->where('is_active', true)->first())) {

            // Services are just disabled by default, the Blockchain stored the hash for that service in each transaction

            if (Inbound::where('id', $inbound->id)->update(['is_active' => false])) {
                return response()->json(['post' => 'SERVICE_DELETED', 'service' => $inbound->hash]);
            }

            return response()->json(['post' => 'SERVICE_NOT_DELETED', 'service' => $inbound->hash]);
        }
                
        return response()->json(['post' => 'INVALID_SERVICE'], 404);
    }

    public function check(Request $request)
    {
        $user = User::where('email', $request->email)->first();
    
        if (empty($user) || !Hash::check($request->password, $user->password)) {
            return response()->json(['post' => 'INVALID_CREDENTIALS'], 401);
        }
                
        // if (!empty($request->service) && (strlen(trim($request->service)) == 32) && ($inbound = Inbound::where('hash', trim($request->service))->where('user_id', $user->id)->where('is_active', true)->first())) {

        if (!empty($request->service) && (strlen(trim($request->service)) == 32) && !empty(trim($request->txid)) && (strlen(trim($request->txid)) == 64) &&
                  ($inbound = Inbound::where('hash', trim($request->service))->where('is_active', true)->first())) {
            
            if ($src = Wallet::where('user_id', $inbound->user_id)->value('address')) {

                $txe = DB::connection('cexplorer')->select('SELECT tx.hash::text
                            FROM tx_out AS tx_outer
                            INNER JOIN tx ON tx.id = tx_outer.tx_id
                            WHERE tx_outer.address = \''.$src.'\' 
                            AND tx.hash::text = \'\x'.trim($request->txid).'\' 
                            LIMIT 1');
                            
                if (!empty($txe)) {
                    $txid = !empty($txe->hash) ? $txe->hash : '';

                    return response()->json(['post' => 'PAYMENT_RECEIVED', 'hash' => $inbound->hash, 'transaction' => $request->txid]);
                }
            }

            return response()->json(['post' => 'PAYMENT_NOT_RECEIVED', 'hash' => $inbound->hash]);
        }
                
        return response()->json(['post' => 'INVALID_PARAMETER'], 404);
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

    public function search(Request $request)
    {
        $service = !empty($request->service) && (strlen(trim($request->service)) == 32) ? trim($request->service) : '';

        if (!empty($service) && ($inbound = Inbound::where('hash', $service)->where('is_active', true)->first())) {

            $service = [];

            $address  = $request->header('X-Payment', '');
            
            $date = new DateTime('now', new DateTimeZone('UTC'));
            $date->modify('+5 minutes');

            $txPrefix = bin2hex(openssl_random_pseudo_bytes(4));

            $service['version']     = 'c402-1.0';
            $service['chain']       = 'Cardano';
            $service['nonce']       = $txPrefix;
            $service['amount']      = bcdiv($inbound->cost, bcpow("10", (string) $inbound->decimals), $inbound->decimals);
            $service['currency']    = $inbound->inbound_hex;
            $service['policy']      = $inbound->policy_id;
            $service['fingerprint'] = $inbound->fingerprint;
            $service['payTo']       = Wallet::where('user_id', $inbound->user_id)->value('address');
            $service['payer']       = $address;
            $service['receiver']    = User::where('id', $inbound->user_id)->value('name');
            $service['facilitator'] = "https://www.wechselstuben.net/api/service";
            $service['expires']     = $date->format('Y-m-d H:i:s') . ' UTC';
            $service['description'] = $inbound->location;

            $txPrefix = '/var/www/stube/storage/app/private/transactions/'.$txPrefix.'/';

            // Return the raw transaction (build transaction output), so that the client can sign / witness

            $address = $request->header('X-Payment', '');
                        
            if (!empty($address) && $this->make_dir($txPrefix)) {

                $transferFee = 200000;

                $spentFee = 0;
                $netwkFee = 0;

                $data = [];
                
                $data['destination']  = Wallet::where('user_id', $inbound->user_id)->value('address');
                $data['policy_id']    = $inbound->policy_id;
                $data['asset_name']   = $inbound->inbound_token;
                $data['asset_hex']    = $inbound->inbound_hex;
                $data['token_number'] = $inbound->cost;
              
                $change = $data['destination'];
            
                $asset = [$data];

                // $metaData = app(ContainerMetadata::class)->generateContainerMetadata($inbound->location);

                $metaData = '';                
                
                $err = app(BabelTransactionController::class)->build_raw_transaction($asset, $address, $change, $data['policy_id'], $data['asset_hex'], "0", $inbound->decimals, $txPrefix, $metaData, $spentFee, $netwkFee, $transferFee);
                
                if ($err['response'] == 'success') {
                    return response()->json($service, 402)->header('X-Payment-Request', $err['message']);
                }

                return response()->json($err, 404);
            }
            
            return response()->json($service, 402)->header('X-Payment-Request', $inbound->hash);
        }
               
        return response()->json(['post' => 'INVALID_SERVICE'], 404);
    }

    public function settle(Request $request)
    {        
        $err = [];
        
        $err['response'] = 'error';
        $err['message']  = 'Wrong command';
        $err['id']       = '';

        $witness = $request->header('X-Payment', '');

        if (empty($witness)) {
            $witness = !empty($request->witness) ? $request->witness : '';    
        }

        $nonce   = !empty($request->nonce) ? $request->nonce : '';
        $payer   = !empty($request->payer) ? $request->payer : '';
        $email   = !empty($request->email) ? $request->email : '';
        $passwd  = !empty($request->password) ? $request->password : '';
        
        if (($nonce !== null) && ($witness !== null)) {
            $txPrefix = '/var/www/stube/storage/app/private/transactions/'.$nonce.'/';        

            $cli = new CardanoCliWrapper($txPrefix, '/usr/local/bin/cardano-cli', '/tmp/node.socket');
                                     
            if (file_exists($cli->txPrefix.'matx.raw')) {
                                
                if (!empty($payer) && ($wallet = Wallet::where('address', $payer)->first())) {
                    
                    if (!empty($email) && !empty($passwd) && ($user = User::where('email', $email)->first())) {
    
                        if (empty($user) || !Hash::check($passwd, $user->password)) {

                            $err['message'] = 'INVALID_CREDENTIALS';
                            
                            return response()->json($err, 401)->header('X-Payment-Response', 'confirmed;tx='.$err['id']);
                        } 
                        
                        if ($wallet->user_id == $user->id) {
                            $cli->witnessTransaction($wallet->signing_key, 'user');

                            $cli->assembleSignature();
                           
                        } else {
                            $err['message'] = 'WALLET_USER_MISMATCH';
                            
                            return response()->json($err, 404)->header('X-Payment-Response', 'confirmed;tx='.$err['id']);
                        }
                    
                    } else {
                        $err['message'] = 'MISSING_CREDENTIALS';
                    
                        return response()->json($err, 401)->header('X-Payment-Response', 'confirmed;tx='.$err['id']);
                    }
                
                } else {
                    // file_put_contents('/tmp/witness', $witness);

                    $cli->assembleRemoteSignature($witness, 6);
                }

                if (file_exists($cli->txPrefix.'matx.signed')) {
                    $retVal = $cli->submitTransaction();

                    // $retVal = 0;

                    if (strlen($retVal) == 64) {
                        
                        if (!empty($wallet)) {
                            $netwkFee = 0;

                            Transaction::Create([
                                    'user_id' => $wallet->user_id,
                                    'transaction_id' => $retVal,
                                    'transaction_fee' => floatval($netwkFee)
                            ]);

                            Cache::tags(['user:' . $wallet->user_id])->flush();   
                        }
                        
                        $err['response'] = 'success';                        
                        $err['id']       = $retVal;
                    }
                }
            }
        }

        $err['message'] = ($err['response'] == 'success') ? 'SERVICE_GRANTED' : 'SERVICE_NOT_GRANTED';
        
        return response()->json($err)->header('X-Payment-Response', 'confirmed;tx='.$err['id']);
    }

}
