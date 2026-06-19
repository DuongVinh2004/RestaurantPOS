<?php

$directories = ['tests', 'database'];

function processDirectory($dir)
{
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    $count = 0;
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $content = file_get_contents($file->getPathname());

            // We want to replace patterns like:
            // '1000.00' -> '1000'
            // "1000.00" -> "1000"
            // number_format($val, 2, '.', '') -> number_format($val, 0, '.', '')
            // '0.00' -> '0'
            // '150000.00' -> '150000'

            $newContent = preg_replace("/'([0-9-]+)\.00'/", "'$1'", $content);
            $newContent = preg_replace('/"([0-9-]+)\.00"/', '"$1"', $newContent);

            // Also replace .50 in some assertions if any, just drop the decimal for assertions
            $newContent = preg_replace("/'([0-9-]+)\.[0-9]{2}'/", "'$1'", $newContent);
            $newContent = preg_replace('/"([0-9-]+)\.[0-9]{2}"/', '"$1"', $newContent);

            // number_format(..., 2, '.', '') -> number_format(..., 0, '.', '')
            $newContent = preg_replace("/number_format\(([^,]+),\s*2,\s*'\.',\s*''\)/", "number_format($1, 0, '.', '')", $newContent);

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
    $total += processDirectory(__DIR__.'/../'.$dir);
}

echo "Modified $total files.\n";
