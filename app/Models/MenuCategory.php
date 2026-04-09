<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasIsDeletedFlag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuCategory extends Model
{
    use HasIsDeletedFlag;

    protected $table = 'menu_categories';
    protected $primaryKey = 'category_id';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'description',
        'sort_order',
        'is_deleted',
    ];

    protected $casts = [
        'category_id' => 'int',
        'sort_order' => 'int',
        'is_deleted' => 'bool',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'category_id', 'category_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('category_id');
    }
}
