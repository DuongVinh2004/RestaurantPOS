<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Models;

use App\Modules\IdentityAccess\Domain\Models\User;
use App\Support\Persistence\HasRowVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFavoriteMenuItem extends Model
{
    use HasRowVersion;

    protected $table = 'user_favorite_menu_items';

    protected $primaryKey = 'favorite_id';

    protected $fillable = [
        'user_id',
        'menu_item_id',
    ];

    protected $casts = [
        'favorite_id' => 'int',
        'user_id' => 'int',
        'menu_item_id' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'row_version' => 'int',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id', 'item_id');
    }
}
