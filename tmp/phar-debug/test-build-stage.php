<?php
$source = realpath(__DIR__ . '/../../build/booking-release/stage/restaurantpos-backend-release-codex-package-check/app/Enums/KitchenTicketStatus.php');
$tar = __DIR__ . '/from-build-stage.tar';
@unlink($tar);
try {
    var_dump($source);
    $phar = new PharData($tar);
    $phar->addFile($source, 'restaurantpos-backend-release-codex-package-check/app/Enums/KitchenTicketStatus.php');
    echo "ok\n";
} catch (Throwable $e) {
    echo get_class($e), "\n", $e->getMessage(), "\n";
}
