<?php

declare(strict_types=1);

namespace App\Services;

class ReleaseBuildMetadataService
{
    /**
     * @return array{commit_sha: string|null, ref_name: string|null, run_id: string|null}
     */
    public function current(): array
    {
        $metadata = (array) config('booking_release.build_metadata', []);

        return [
            'commit_sha' => $this->normalize($metadata['commit_sha'] ?? null),
            'ref_name' => $this->normalize($metadata['ref_name'] ?? null),
            'run_id' => $this->normalize($metadata['run_id'] ?? null),
        ];
    }

    private function normalize(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
