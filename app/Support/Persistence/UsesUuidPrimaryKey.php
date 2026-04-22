<?php

declare(strict_types=1);

namespace App\Support\Persistence;

trait UsesUuidPrimaryKey
{
    /**
     * Eloquent calls initialize{TraitName}() when constructing a model instance.
     * Keep key metadata here to avoid PHP 8.2+ property collisions on base models.
     */
    protected function initializeUsesUuidPrimaryKey(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }
}
