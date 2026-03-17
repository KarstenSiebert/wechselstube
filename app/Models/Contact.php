<?php

namespace App\Models;

use App\Models\User;
use App\Services\FieldEncryption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Contact extends Model
{
    use HasFactory;

    /**
     * Summary of fillable
     * @var array
     */
    protected $fillable = [
        'user_id',
        'name',
        'address'
    ];

    /**
     * Summary of hidden
     * @var array
     */
    protected $hidden = [
        'user_id'
    ];

    public function user(): BelongsTo 
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted()
    {
        static::saving(function ($model) {
            $crypto = new FieldEncryption();
            
            if ($model->address) {
                // $model->address = $crypto->encrypt($model->address);
            }            
        });

        static::retrieved(function ($model) {
            $crypto = new FieldEncryption();

            if ($model->address) {
                // $model->address = $crypto->decrypt($model->address);
            }            
        });
    }

}
