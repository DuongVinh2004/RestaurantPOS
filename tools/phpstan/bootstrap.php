<?php

declare(strict_types=1);

if ((string) ini_get('memory_limit') !== '-1') {
    ini_set('memory_limit', '1G');
}
