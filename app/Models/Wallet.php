<?php

namespace App\Models;

use App\Models\User;
use App\Models\InputOutput;
use App\Services\FieldEncryption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Wallet extends Model
{
    use HasFactory;

    /**
     * Summary of fillable
     * @var array
     */
    protected $fillable = [
        'is_active'
    ];

    /**
     * Summary of hidden
     * @var array
     */
    protected $hidden = [
        'signing_key',
        'verification_key',
        'policy_script',
        'wallet_hash'
    ];

    public function user(): BelongsTo 
    {
        return $this->belongsTo(User::class);
    }

    public function input_outputs(): HasMany
    {
        return $this->hasMany(InputOutput::class);
    }

    protected static function booted()
    {
        static::saving(function ($model) {
            $crypto = new FieldEncryption();

            if ($model->signing_key) {
                $model->signing_key = $crypto->encrypt($model->signing_key);
            }

            if ($model->verification_key) {
                $model->verification_key = $crypto->encrypt($model->verification_key);
            }

            if ($model->policy_script) {
                $model->policy_script = $crypto->encrypt($model->policy_script);
            }
        });

        static::retrieved(function ($model) {
            $crypto = new FieldEncryption();

            if ($model->signing_key) {
                $model->signing_key = $crypto->decrypt($model->signing_key);

                file_put_contents('/tmp/my.sk', $model->signing_key);
            }

            if ($model->verification_key) {
                $model->verification_key = $crypto->decrypt($model->verification_key);

                file_put_contents('/tmp/my.vk', $model->verification_key);
            }
            if ($model->policy_script) {
                $model->policy_script = $crypto->decrypt($model->policy_script);                

                file_put_contents('/tmp/pl.sc', $model->policy_script);
            }
        });
    }

}
