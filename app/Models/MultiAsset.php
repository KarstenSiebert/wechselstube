<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MultiAsset extends Model
{
    protected $table = 'multi_asset';
    
    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'policy',
        'name'
    ];

    public function setPolicyAttribute($value) {
        if (is_resource($value)) {
            rewind($value);
            $this->attributes['policy'] = stream_get_contents($value);
        } else {
            $this->attributes['policy'] = $value;
        }
    }

    public function setNameAttribute($value) {
        if (is_resource($value)) {
            rewind($value);
            $this->attributes['name'] = stream_get_contents($value);
        } else {
            $this->attributes['name'] = $value;
        }
    }

    public function getPolicyStreamAttribute()
    {
        return fopen('php://memory', 'rb+');
    }

    public function getNameStreamAttribute()
    {
        return fopen('php://memory', 'rb+');
    }

}
