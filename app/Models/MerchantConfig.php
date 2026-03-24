<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantConfig extends Model
{
    protected $table = 'merchant_configs';

    protected $fillable = [
        'provider',       // uber_eats | deliveroo | just_eat
        'client_id',
        'client_secret',
        'api_key',        // used by Just Eat
        'store_id',
        'webhook_secret',
        'is_live',        // false = mock mode, true = live
    ];

    protected $casts = [
        'is_live' => 'boolean',
    ];
}
