<?php

namespace App\Models;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InputOutput extends Model
{
    use HasFactory;

    /**
     * Summary of fillable
     * @var array
     */
    protected $fillable = [
        'user_id',
        'wallet_id',
        'inputs',
        'outputs',
        'change'
    ];

    /**
     * Summary of hidden
     * @var array
     */
    protected $hidden = [
        'user_id',
        'wallet_id',        
        'inputs',
        'outputs',
        'change'
    ];

    public function user(): BelongsTo 
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo 
    {
        return $this->belongsTo(Wallet::class);
    }

}
