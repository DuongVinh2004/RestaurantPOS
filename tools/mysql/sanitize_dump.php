<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Support/PortableSqlSanitizer.php';

use App\Support\PortableSqlSanitizer;

if ($argc < 3) {
    fwrite(STDERR, "Usage: php tools/mysql/sanitize_dump.php <input.sql> <output.sql>\n");
    exit(1);
}

$input = $argv[1];
$output = $argv[2];

if (! is_file($input)) {
    fwrite(STDERR, "Input file not found: {$input}\n");
    exit(1);
}

$sql = file_get_contents($input);
if ($sql === false) {
    fwrite(STDERR, "Unable to read input file: {$input}\n");
    exit(1);
}

try {
    $sanitized = PortableSqlSanitizer::sanitize($sql);
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

if (file_put_contents($output, $sanitized['sql']) === false) {
    fwrite(STDERR, "Unable to write output file: {$output}\n");
    exit(1);
}

fwrite(STDOUT, sprintf(
    "Portable dump written to %s%s\n",
    $output,
    ($sanitized['changed'] ? ' (sanitized)' : ' (no changes)')
));
