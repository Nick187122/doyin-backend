<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HeroImage extends Model
{
    use HasFactory;

    protected $fillable = ['image_path', 'title', 'is_active', 'order'];
    
    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer'
    ];
}
