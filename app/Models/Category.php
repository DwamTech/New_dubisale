<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'name_en',
        'icon',
        'default_image',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
    protected $appends = ['icon_url', 'default_image_url'];

    public function getIconUrlAttribute(): ?string
    {
        if (!$this->icon) {
            return null;
        }

        return asset('storage/uploads/categories/' . $this->icon);
    }

    public function getDefaultImageUrlAttribute(): ?string
    {
        if (!$this->default_image) {
            return null;
        }

        return asset('storage/uploads/categories/' . $this->default_image);
    }
    public function listings()
    {
        return $this->hasMany(\App\Models\Listing::class);
    }

    public function planPrice()
    {
        return $this->hasOne(\App\Models\CategoryPlanPrice::class);
    }
}
