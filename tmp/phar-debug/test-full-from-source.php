<?php
$base = realpath(__DIR__ . '/../..');
$includePaths = [
  'artisan', 'composer.json', '.env.example', 'app', 'bootstrap', 'build/api-consumer', 'config', 'database',
  'package.json', 'phpunit.xml', 'public/index.php', 'routes', 'scripts', 'staff-web', 'storage/app/booking_release',
  'tests', 'tools/bootstrap_booking.php', 'docs/runbooks', 'README.md', 'tools/mysql', 'vite.config.js', 'db_all.sql'
];
$tar = __DIR__ . '/full-from-source.tar';
@unlink($tar);
$phar = new PharData($tar);
$count = 0;
try {
  foreach ($includePaths as $includePath) {
    $source = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $includePath);
    if (is_file($source)) {
      $phar->addFile(realpath($source), 'debug/' . str_replace('\\', '/', $includePath));
      $count++;
      continue;
    }
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($iterator as $item) {
      if (!$item->isFile()) {
        continue;
      }
      $path = $item->getRealPath();
      $relative = substr($path, strlen($source) + 1);
      $archive = 'debug/' . trim(str_replace('\\', '/', $includePath . '/' . $relative), '/');
      $phar->addFile($path, $archive);
      $count++;
      if ($count % 500 === 0) {
        echo "added=$count\n";
      }
    }
  }
  echo "ok files=$count\n";
} catch (Throwable $e) {
  echo "failed files=$count\n";
  echo get_class($e), "\n", $e->getMessage(), "\n";
}
