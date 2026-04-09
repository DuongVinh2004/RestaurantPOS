<?php

declare(strict_types=1);

namespace App\Models\Concerns;

trait HasRowVersion
{
    /**
     * Release-grade persisted row_version advancement is governed by MySQL schema triggers.
     *
     * This trait remains as an application-side compatibility helper so Eloquent save() paths
     * keep in-memory models and non-schema test harnesses aligned with the same monotonic contract.
     */
    public static function bootHasRowVersion(): void
    {
        static::creating(function ($model): void {
            $column = $model->getRowVersionColumn();
            if ($model->getAttribute($column) === null) {
                $model->setAttribute($column, 1);
            }
        });

        static::updating(function ($model): void {
            $column = $model->getRowVersionColumn();
            if ($model->isDirty($column)) {
                return;
            }

            $current = $model->getOriginal($column);
            if ($current === null) {
                $current = $model->getAttribute($column);
            }

            $next = max(1, (int) $current) + 1;
            $model->setAttribute($column, $next);
        });
    }

    public function getRowVersionColumn(): string
    {
        return 'row_version';
    }

    public function getRowVersion(): ?int
    {
        $column = $this->getRowVersionColumn();

        /** @var int|null $value */
        $value = $this->getAttribute($column);

        return $value;
    }
}
