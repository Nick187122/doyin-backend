<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserInteraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'name',
        'email',
        'content',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];
}
