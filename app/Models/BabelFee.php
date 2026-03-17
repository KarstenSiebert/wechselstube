<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BabelFee extends Model
{
     use HasFactory;

    /**
     * Summary of fillable
     * @var array
     */
    protected $fillable = [
        'user_id',
        'babelfee_token',
        'policy_id',
        'fingerprint',
        'rate',
        'decimals',
        'is_active'
    ];

    /**
     * Summary of hidden
     * @var array
     */
    protected $hidden = [
        'user_id',
        'rate',
        'is_active'
    ];

    public function user(): BelongsTo 
    {
        return $this->belongsTo(User::class);
    }
}
