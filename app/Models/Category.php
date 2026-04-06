<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'is_pump', 'has_ideal_power'];

    protected $casts = [
        'is_pump' => 'boolean',
        'has_ideal_power' => 'boolean',
    ];

    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
