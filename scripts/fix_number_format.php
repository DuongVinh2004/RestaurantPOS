<?php

$dir = __DIR__.'/../app/Modules';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
$files = [];

foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $files[] = $file->getPathname();
    }
}

foreach ($files as $file) {
    $content = file_get_contents($file);
    if (str_contains($content, 'number_format(') && str_contains($content, "2, '.', ''")) {
        $newContent = str_replace("2, '.', ''", "0, '.', ''", $content);
        file_put_contents($file, $newContent);
        echo "Updated: $file\n";
    }
}
echo "Done.\n";
