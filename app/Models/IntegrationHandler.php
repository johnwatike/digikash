<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationHandler extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'is_enabled',
        'config',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'config'     => 'array',
    ];
}
