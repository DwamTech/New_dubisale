<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class Make extends Model
{
    //
    use HasFactory;

    protected $fillable = ['name', 'name_en'];

    public function models()
    {
        return $this->hasMany(CarModel::class);
    }

    public function cars()
    {
        return $this->hasMany(Car::class);
    }
}
