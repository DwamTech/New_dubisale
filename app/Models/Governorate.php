<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Governorate extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'name_en'];

    public function cities()
    {
        return $this->hasMany(City::class);
    }

    public function cars()
    {
        return $this->hasMany(Car::class);
    }
    public function listings()
    {
        return $this->hasMany(Listing::class, 'governorate_id');
    }
}
