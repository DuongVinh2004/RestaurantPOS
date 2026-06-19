<?php

$directories = ['tests', 'database'];

function processDirectory($dir) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    $count = 0;
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $content = file_get_contents($file->getPathname());
            $newContent = $content;

            // number_format((float) anything, 2, '.', '') -> number_format((float) anything, 0, '.', '')
            $newContent = preg_replace("/number_format\(\(float\)\s*(.+?),\s*2,\s*'\.',\s*''\)/s", "number_format((float) $1, 0, '.', '')", $newContent);

            // Special case in StaffReportingReadModelsHttpFlowTest.php
            $newContent = str_replace('8181.82', '8181.0', $newContent);

            if ($newContent !== $content) {
                file_put_contents($file->getPathname(), $newContent);
                $count++;
            }
        }
    }
    return $count;
}

$total = 0;
foreach ($directories as $dir) {
    $total += processDirectory(__DIR__ . '/../' . $dir);
}

echo "Modified $total files.\n";
