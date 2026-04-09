<?php

declare(strict_types=1);

$catalogPath = base_path('config/booking_performance_verification_matrix.json');
$catalog = [];

if (is_file($catalogPath)) {
    $decoded = json_decode((string) file_get_contents($catalogPath), true, 512, JSON_THROW_ON_ERROR);
    if (is_array($decoded)) {
        $catalog = $decoded;
    }
}

return [
    'catalog_path' => $catalogPath,
    'artifact_root' => (string) ($catalog['artifact_root'] ?? 'storage/app/booking_release/performance_verification'),
    'warning_threshold_ratio' => (float) ($catalog['warning_threshold_ratio'] ?? 0.9),
    'groups' => (array) ($catalog['groups'] ?? []),
    'profiles' => (array) ($catalog['profiles'] ?? []),
    'scenarios' => array_values((array) ($catalog['scenarios'] ?? [])),
];
