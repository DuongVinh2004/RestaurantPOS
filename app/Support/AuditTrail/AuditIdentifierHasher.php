<?php

declare(strict_types=1);

namespace App\Support\AuditTrail;

final class AuditIdentifierHasher
{
    public function hash(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '[redacted]';
        }

        $key = (string) (config('audit.hash_key') ?: config('app.key'));
        if ($key === '') {
            return '[redacted]';
        }

        return 'hmac-sha256:'.hash_hmac('sha256', $value, $key);
    }
}
