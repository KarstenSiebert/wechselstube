<?php

namespace App\Http\Controllers;

use DB;
use App\Models\Tx;
use Inertia\Inertia;
use App\Models\History;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator;

class HistoryController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', null);
    
        $page = (int) $request->input('page', 1);
    
        $perPage = 10;

        $user = auth()->user();
        
        $address = $user->wallet->address;
  
        $sql = "WITH txs AS (
            SELECT 
                tx.id AS tx_id,
                encode(tx.hash, 'hex') AS tx_hash,
                block.time AS timestamp,
                COALESCE((
                    SELECT SUM(o.value) 
                    FROM tx_out o
                    WHERE o.tx_id = tx.id AND o.address = :address
                ), 0) AS incoming_amount,
                COALESCE((
                    SELECT SUM(o.value)
                    FROM tx_in i
                    JOIN tx_out o ON o.tx_id = i.tx_out_id AND o.index = i.tx_out_index
                    WHERE i.tx_in_id = tx.id AND o.address = :address
                ), 0) AS outgoing_amount
            FROM tx
            JOIN block ON block.id = tx.block_id
                WHERE tx.id IN (
                    SELECT tx_id FROM tx_out WHERE address = :address
                    UNION
                    SELECT tx_in_id
                    FROM tx_in
                    JOIN tx_out ON tx_out.tx_id = tx_in.tx_out_id AND tx_out.index = tx_in.tx_out_index
                    WHERE tx_out.address = :address
                )
            )
            SELECT
                tx_hash,
                timestamp,
                tx_id,
                incoming_amount,
                outgoing_amount,
                CASE 
                    WHEN incoming_amount > 0 AND outgoing_amount > 0 THEN 'internal'
                    WHEN incoming_amount > 0 THEN 'incoming'
                    WHEN outgoing_amount > 0 THEN 'outgoing'
                    ELSE 'other'
                END AS direction,
                (incoming_amount - outgoing_amount) AS balance_change
            FROM txs
            ORDER BY timestamp DESC LIMIT 500
        ";

        $items = Cache::tags(['user:' . $user->id])->remember('history', 600, function () use ($sql, $address) {
            return collect(DB::connection('cexplorer')->select($sql, ['address' => $address]));
        });
        
        // dd($items);

        if (!empty($search)) {   
            $searchTerms = preg_split('/\+/', strtolower(trim($search)));

            $filtered = $items->filter(function($item) use ($searchTerms) {

                $tx_hash   = strtolower($item->tx_hash ?? '');                
                $incoming  = strtolower($item->incoming_amount ?? '');
                $outgoing  = strtolower($item->outgoing_amount ?? '');
                $balance   = strtolower($item->balance_change ?? '');
                $timestamp = strtolower($item->timestamp ?? '');
                $direction = strtolower($item->direction ?? '');
                                                
                return collect($searchTerms)->contains(fn($term) =>
                    str_contains($tx_hash, $term) || str_contains($incoming, $term) || str_contains($outgoing, $term) || str_contains($direction, $term) || str_contains($balance, $term) || str_contains($timestamp, $term));
            });
        
        } else {
            $filtered = $items;
        }
        
        if ($filtered->count()) {

            $paginated = new LengthAwarePaginator(
                    $filtered->forPage($page, $perPage)->values(),
                    $filtered->count(),
                    $perPage,
                    $page,
                    ['path' => request()->url(), 'query' => $request->query()]
                );
        
        } else {
            $paginated = new LengthAwarePaginator([], 0, $perPage, $page);            
        }
        
        return Inertia::render('history/History', [
            'history' => [
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

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(History $history)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(History $history)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, History $history)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(History $history)
    {
        //
    }

   public function confirm(): void
    {
        $pendingPayments = Payment::where('status', 'pending')->limit(10)->get(['id', 'user_id', 'asset_hex', 'policy_id', 'quantity', 'decimals']);

        // dd($pendingPayments);
        
        $payments = [];

        foreach($pendingPayments as $pending) {            
            $payments[] = ['id' => $pending->id, 'user_id' => $pending->user_id, 'asset_hex' => $pending->asset_hex, 'policy_id' => $pending->policy_id, 'quantity' => $pending->quantity, 'decimals' => $pending->decimals, 'status' => 'pending'];
        }

        // dd($payments);

        $txHashes = [];

        $transactions = Transaction::where('is_confirmed', false)->limit(30)->pluck('transaction_id');
        
        // dd($transactions);

        foreach($transactions as $transaction) {
            $txHashes[] = '\x'.$transaction;
        }

        $txs = Tx::on('cexplorer')->with('txOuts.maTxOuts.multiAsset')->whereIn('hash', $txHashes)->get();        
        
        // dd($txs);

        // dd($payments);

        foreach ($payments as &$payment) {
            foreach ($txs as $tx) {
                foreach ($tx->txOuts as $txOut) {
                    foreach ($txOut->maTxOuts as $maTxOut) {
                        $asset = $maTxOut->multiAsset;

                        $binPolicy = is_resource($asset->policy) ? stream_get_contents($asset->policy) : $asset->policy;

                        if (is_resource($asset->policy)) rewind($asset->policy);

                        $hexPolicy = bin2hex($binPolicy);

                        $binName = is_resource($asset->name) ? stream_get_contents($asset->name) : $asset->name;
    
                        if (is_resource($asset->name)) rewind($asset->name);

                        $hexName = bin2hex($binName);

                        $txHash = $tx->hash;

                        if (is_resource($txHash)) {
                            rewind($txHash);
                            $txHash = stream_get_contents($txHash);
                        }

                        $txHashHex = bin2hex($txHash);
                        
                        $txId = Transaction::where('transaction_id', $txHashHex)->value('id');
                        
                        // if ($hexPolicy === $payment['policy_id'] && $hexName === $payment['asset_hex'] && $maTxOut->quantity == $payment['quantity']) {

                        if ($hexPolicy === $payment['policy_id'] && $hexName === $payment['asset_hex']) {      

                            // dd($txId, $txHashHex, $hexPolicy, $payment['policy_id'], $hexName, $payment['asset_hex'] , $maTxOut->quantity, $payment['quantity']);

                            Payment::where('id', $payment['id'])->update(['transaction_id' => $txId, 'status' => 'paid']);

                            if(Transaction::where('id', $txId)->where('is_confirmed', false)->update(['is_confirmed' => true])) {
                                Cache::tags(['user:' . $payment['user_id']])->flush();
                            }
                            
                            break 3;
                        }
                    }
                }
            }
        }
        
        $txs = Tx::on('cexplorer')->whereIn('hash', $txHashes)->get();

        foreach ($txs as $tx) {
            $txHash = $tx->hash;

            if (is_resource($txHash)) {
                rewind($txHash);
                $txHash = stream_get_contents($txHash);
            }

            $txHashHex = bin2hex($txHash);
         
            // Transaction::where('transaction_id', $txHashHex)->where('is_confirmed', false)->update(['is_confirmed' => true]);

            if ($tran = Transaction::where('transaction_id', $txHashHex)->where('is_confirmed', false)->first()) {
                $tran->update(['is_confirmed' => true]);
    
                Cache::tags(['user:' . $tran->user_id])->flush();
            }
        }
    }

}
