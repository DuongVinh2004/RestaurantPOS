<?php

declare(strict_types=1);

namespace App\Models\Concerns;

trait UsesUuidPrimaryKey
{
    /**
     * Eloquent sẽ gọi initialize{TraitName}() khi khởi tạo model instance.
     * Cách này tránh xung đột property với base Model trong PHP 8.2+.
     */
    protected function initializeUsesUuidPrimaryKey(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }
}
