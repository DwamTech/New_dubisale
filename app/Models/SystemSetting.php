<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $table = 'system_settings';

    protected $fillable = [
        'key',
        'value',
        'value_en',
        'type',
        'group',
        'label',
        'meta',
        'autoload',
    ];

    protected $casts = [
        'meta' => 'array',
        'autoload' => 'boolean',
    ];
}
