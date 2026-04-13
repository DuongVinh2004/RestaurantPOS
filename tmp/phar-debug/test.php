<?php
$source = __DIR__ . '/../app/Enums/KitchenTicketStatus.php';
$tar = __DIR__ . '/single-file.tar';
@unlink($tar);
try {
    $phar = new PharData($tar);
    $phar->addFile($source, 'debug/app/Enums/KitchenTicketStatus.php');
    echo "ok\n";
} catch (Throwable $e) {
    echo get_class($e), "\n", $e->getMessage(), "\n";
}
