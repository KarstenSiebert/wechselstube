<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Inbound extends Model
{
    use HasFactory;

    /**
     * Summary of fillable
     * @var array
     */
    protected $fillable = [
        'user_id',
        'location',
        'inbound_token',
        'inbound_hex',
        'policy_id',
        'fingerprint',
        'cost',
        'decimals',
        'hash',
        'is_active'
    ];

    /**
     * Summary of hidden
     * @var array
     */
    protected $hidden = [
        'user_id',
        'fingerprint',
        'hash',
        'is_active'
    ];

    public function user(): BelongsTo 
    {
        return $this->belongsTo(User::class);
    }
    
}
