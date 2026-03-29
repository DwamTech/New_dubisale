<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryMainSection extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'name_en',
        'sort_order',
        'is_active',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function subSections()
    {
        return $this->hasMany(CategorySubSection::class, 'main_section_id');
    }

    public function listings()
    {
        return $this->hasMany(Listing::class, 'main_section_id');
    }
}
