<?php

/*
 * PHP AMQP-Compat - php-amqp/ext-amqp compatibility.
 * Copyright (c) Dan Phillimore (asmblah)
 * https://github.com/asmblah/php-amqp-compat/
 *
 * Released under the MIT license.
 * https://github.com/asmblah/php-amqp-compat/raw/main/MIT-LICENSE.txt
 */

declare(strict_types=1);

namespace Asmblah\PhpAmqpCompat\Tests\Functional\Reference\Util;

use Asmblah\PhpAmqpCompat\AmqpManager;
use Asmblah\PhpAmqpCompat\Configuration\Configuration;
use RuntimeException;

// Allow the main library bootstrap to silently allow us to continue.
const PHP_AMQP_COMPAT = true;

require_once __DIR__ . '/../../../../vendor/autoload.php';

CodeShifts::install();
AmqpManager::setConfiguration(new Configuration(null, new TestErrorReporter()));

if (isset($argv)) {
    // Force the actual test script to be loaded via file:// stream wrapper,
    // so that PHP Code Shift can transpile it.
    if (count($argv) === 0) {
        throw new RuntimeException('Missing test script');
    }

    require /*_once*/ $argv[0];

    // We already ran the actual script just above, so now exit otherwise it will be run again.
    exit;
}
