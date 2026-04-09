<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TableTemplate extends Model
{
    protected $table = 'table_templates';
    protected $primaryKey = 'template_id';

    public $timestamps = false;

    protected $fillable = [
        'template_code',
        'seats',
        'description',
    ];

    protected $casts = [
        'template_id' => 'int',
        'template_code' => 'string',
        'seats' => 'int',
        'description' => 'string',
    ];

    public function tables(): HasMany
    {
        return $this->hasMany(RestaurantTable::class, 'template_id', 'template_id');
    }
}
