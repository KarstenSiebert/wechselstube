<?php

namespace App\Models;

use App\Models\MultiAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaTxOut extends Model
{
    protected $table = 'ma_tx_out';

    protected $primaryKey = 'id';
    
    public $timestamps = false;

    public function multiAsset(): BelongsTo
    {
        return $this->belongsTo(MultiAsset::class, 'ident');
    }

    public function txOut()
    {
        return $this->belongsTo(TxOut::class, 'tx_out_id');
    }

}
