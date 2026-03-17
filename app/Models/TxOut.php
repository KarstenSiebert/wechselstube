<?php

namespace App\Models;

use DB;
use App\Models\MaTxOut;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TxOut extends Model
{
    protected $table = 'tx_out';

    protected $primaryKey = 'id';
    
    public $timestamps = false;

    public function maTxOuts(): HasMany
    {
        return $this->hasMany(MaTxOut::class, 'tx_out_id');
    }

    /**
     * Prüft, ob in einer Wallet-Adresse ein bestimmtes Token existiert.
     *
     * @param string $walletAddress
     * @param string $policyId
     * @param string $assetHex
     * @return bool
     */
 
    public static function hasToken(string $walletAddress, string $policyId, string $assetHex): bool
    {
        return DB::connection('cexplorer')->table('tx_out')
            ->join('ma_tx_out', 'ma_tx_out.tx_out_id', '=', 'tx_out.id')
            ->join('multi_asset', 'multi_asset.id', '=', 'ma_tx_out.ident')
            ->where('tx_out.address', $walletAddress)            
            ->where(DB::raw("encode(multi_asset.policy, 'hex')"), $policyId)
            ->where(DB::raw("encode(multi_asset.name, 'hex')"), $assetHex)
            ->exists();
    }

}
