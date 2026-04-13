<?php
$root = realpath(__DIR__ . '/restaurantpos-backend-release-codex-package-check/app');
$tar = __DIR__ . '/staged-app-only.tar';
@unlink($tar);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$count = 0;
try {
    $phar = new PharData($tar);
    foreach ($iterator as $item) {
        if (!$item->isFile()) {
            continue;
        }
        $count++;
        $path = $item->getPathname();
        $archive = 'restaurantpos-backend-release-codex-package-check/app/' . str_replace('\\', '/', substr($path, strlen($root) + 1));
        $phar->addFile($path, $archive);
    }
    echo "ok files=$count\n";
} catch (Throwable $e) {
    echo "failed files=$count\n";
    echo get_class($e), "\n", $e->getMessage(), "\n";
}
