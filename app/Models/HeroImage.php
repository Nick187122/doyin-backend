<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeroImage extends Model
{
    protected $fillable = ['image_path', 'title', 'is_active', 'order'];
    
    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer'
    ];
}
