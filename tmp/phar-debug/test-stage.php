<?php
$source = __DIR__ . '/stage/app/Enums/KitchenTicketStatus.php';
$tar = __DIR__ . '/single-file-stage.tar';
@unlink($tar);
try {
    var_dump($source);
    $phar = new PharData($tar);
    $phar->addFile($source, 'debug/app/Enums/KitchenTicketStatus.php');
    echo "ok\n";
} catch (Throwable $e) {
    echo get_class($e), "\n", $e->getMessage(), "\n";
}
