<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class MenuItem extends Model
{
    protected $table = 'menu_items';

    protected $primaryKey = 'item_id';

    protected $fillable = [
        'category_id',
        'code',
        'name',
        'description',
        'img_url',
        'is_available',
        'is_preorder_enabled',
        'preorder_quota_per_day',
        'preorder_cutoff_minutes',
        'is_combo',
        'is_best_seller',
        'compare_at_price_amount',
        'serving_size',
        'combo_items_json',
    ];

    protected $casts = [
        'item_id' => 'int',
        'category_id' => 'int',
        'is_available' => 'bool',
        'is_preorder_enabled' => 'bool',
        'preorder_quota_per_day' => 'int',
        'preorder_cutoff_minutes' => 'int',
        'is_combo' => 'bool',
        'is_best_seller' => 'bool',
        'compare_at_price_amount' => 'decimal:0',
        'serving_size' => 'int',
        'combo_items_json' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    private static ?bool $supportsPreorderColumns = null;

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'category_id', 'category_id');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(MenuItemPrice::class, 'item_id', 'item_id');
    }

    public function comboComponents(): HasMany
    {
        return $this->hasMany(MenuItemComboComponent::class, 'combo_item_id', 'item_id');
    }

    public function modifierGroups(): BelongsToMany
    {
        return $this->belongsToMany(MenuModifierGroup::class, 'menu_item_modifier_groups', 'item_id', 'group_id')
            ->withPivot('sort_order')
            ->orderBy('menu_item_modifier_groups.sort_order');
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function priceAt(?\DateTimeInterface $at = null): ?MenuItemPrice
    {
        $at = $at ? Carbon::instance(\DateTimeImmutable::createFromInterface($at)) : now();

        return $this->prices()
            ->effectiveAt($at)
            ->orderByDesc('effective_from')
            ->orderByDesc('price_id')
            ->first();
    }

    public static function supportsPreorderColumns(): bool
    {
        if (self::$supportsPreorderColumns !== null) {
            return self::$supportsPreorderColumns;
        }

        self::$supportsPreorderColumns = Schema::hasColumns('menu_items', [
            'is_preorder_enabled',
            'preorder_quota_per_day',
            'preorder_cutoff_minutes',
        ]);

        return self::$supportsPreorderColumns;
    }
}
