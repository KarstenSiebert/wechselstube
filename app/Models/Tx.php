<?php

namespace App\Models;

use App\Models\TxOut;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tx extends Model
{
    protected $table = 'tx';

    protected $primaryKey = 'id';
    
    public $timestamps = false;

    public function txOuts(): HasMany
    {
        return $this->hasMany(TxOut::class, 'tx_id');
    }

}
