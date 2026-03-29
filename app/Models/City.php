<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class City extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'name_en', 'governorate_id'];

    public function governorate()
    {
        return $this->belongsTo(Governorate::class);
    }

    public function listings()
    {
        return $this->hasMany(Listing::class, 'city_id');
    }

    public function cars()
    {
        return $this->hasMany(Car::class);
    }
}
