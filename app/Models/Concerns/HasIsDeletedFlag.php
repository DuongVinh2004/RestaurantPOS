<?php

declare(strict_types=1);

namespace App\Models\Concerns;

trait HasIsDeletedFlag
{
    public function scopeNotDeleted($query)
    {
        return $query->where('is_deleted', false);
    }

    public function scopeDeleted($query)
    {
        return $query->where('is_deleted', true);
    }
}
