<?php

declare(strict_types=1);

namespace LHcze\FqcnStripper\Tests;

require_once __DIR__ . '/../vendor/autoload.php';

use LHcze\FqcnStripper\FqcnStripper;
use LogicException;

try {
    echo FqcnStripper::strip('App\\Üser', FqcnStripper::MULTIBYTE);
} catch (LogicException) {
    echo 'PASS: Caught expected exception';
    exit(0);
}

echo 'FAIL: No exception thrown';
exit(1);
