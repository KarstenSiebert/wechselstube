<?php

namespace App\Models;

use App\Models\User;
use App\Models\Contact;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Auth;

class Payment extends Model
{
    use HasFactory;

    /**
     * Summary of fillable
     * @var array
     */
    protected $fillable = [
        'user_id',
        'remote_id',
        'transaction_id',
        'asset_hex',
        'policy_id',
        'fingerprint',
        'quantity',
        'decimals',
        'due_date',
        'status'
    ];

    /**
     * Summary of hidden
     * @var array
     */
    protected $hidden = [
    
    ];
    
    public function user(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function remote(): BelongsTo {
        return $this->belongsTo(User::class, 'remote_id');
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class);
    }

    protected function direction(): Attribute
    {
        return Attribute::make(
            get: fn () =>
                $this->user_id === Auth::id()
                    ? 'outgoing'
                    : 'incoming'
        );
    }

    protected function remoteUserName(): Attribute
    {
        return Attribute::make(
            get: fn () =>
                $this->user_id === Auth::id()
                    ? $this->remote?->name
                    : $this->user?->name
        );
    }

    protected function remoteUserAddress(): Attribute
    {
        return Attribute::make(
            get: fn () =>
                $this->user_id === Auth::id()
                    ? $this->remote?->wallet->address
                    : $this->user?->wallet->address
        );
    }

}
