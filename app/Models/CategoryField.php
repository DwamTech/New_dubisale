<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryField extends Model
{
    protected $table = 'category_fields';

    protected $fillable = [
        'category_slug',
        'field_name',
        'display_name',
        'display_name_en',
        'type',
        'required',
        'filterable',
        'options',
        'options_en',
        'rules_json',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'options'    => 'array',
        'options_en' => 'array',
        'rules_json' => 'array',
        'required'   => 'boolean',
        'filterable' => 'boolean',
        'is_active'  => 'boolean',
    ];
}
