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
use Nytris\Boot\BootConfig;
use Nytris\Boot\PlatformConfig;
use Nytris\Nytris;
use RuntimeException;
use Tasque\Core\Scheduler\ContextSwitch\NTockStrategy;
use Tasque\EventLoop\TasqueEventLoopPackage;
use Tasque\TasquePackage;

// Allow the main library bootstrap to silently allow us to continue.
const PHP_AMQP_COMPAT = true;

require_once __DIR__ . '/../../../../vendor/autoload.php';

CodeShifts::install();

AmqpManager::setConfiguration(
    new Configuration(
        errorReporter: new TestErrorReporter(CodeShifts::getContextResolver())
    )
);

$bootConfig = new BootConfig(new PlatformConfig(dirname(__DIR__, 4) . '/var/nytris/'));
$bootConfig->installPackage(new TasquePackage(new NTockStrategy(1)));
$bootConfig->installPackage(new TasqueEventLoopPackage());

Nytris::boot($bootConfig);

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
