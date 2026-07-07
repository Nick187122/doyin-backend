<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'description',
        'image_path',
        'max_flow_rate',
        'max_height',
        'recommended_depth',
        'ideal_power',
        'performance_curves',
        'in_stock',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    protected function casts(): array
    {
        return [
            'in_stock' => 'boolean',
            'performance_curves' => 'array',
        ];
    }
}
