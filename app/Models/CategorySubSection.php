<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategorySubSection extends Model
{
    protected $table = 'category_sub_section';
    protected $fillable = [
        'category_id',
        'main_section_id',
        'name',
        'name_en',
        'sort_order',
        'is_active',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function mainSection()
    {
        return $this->belongsTo(CategoryMainSection::class, 'main_section_id');
    }

    public function listings()
    {
        return $this->hasMany(Listing::class, 'sub_section_id');
    }
}
