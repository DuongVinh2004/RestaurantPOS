<?php

declare(strict_types=1);

namespace App\Platform\Delivery\Release\Application\Verifiers;

final class PortableSqlSanitizer
{
    /**
     * @return array{sql:string, changed:bool}
     */
    public static function sanitize(string $sql): array
    {
        $patterns = [
            '/\/\*!50017 DEFINER=`[^`]+`@`[^`]+`\*\/\s*/i',
            '/CREATE\s+DEFINER=`[^`]+`@`[^`]+`\s+/i',
            '/ALTER\s+DEFINER=`[^`]+`@`[^`]+`\s+/i',
            '/DEFINER=`[^`]+`@`[^`]+`\s*/i',
        ];
        $replacements = [
            '',
            'CREATE ',
            'ALTER ',
            '',
        ];

        $sanitized = preg_replace($patterns, $replacements, $sql);
        if ($sanitized === null) {
            throw new \RuntimeException('Regex processing failed while sanitizing SQL dump.');
        }

        return [
            'sql' => $sanitized,
            'changed' => ($sanitized !== $sql),
        ];
    }
}
