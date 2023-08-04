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

namespace Asmblah\PhpAmqpCompat;

use Asmblah\PhpAmqpCompat\Bridge\AmqpBridge;

if (extension_loaded('amqp') && !defined('PHP_AMQP_COMPAT')) {
    AmqpManager::getConfiguration()->getErrorReporter()
        ->raiseWarning(
            'ext-amqp must be uninstalled to use php-amqp-compat, ext-amqp will still be used'
        );

    return;
}

// Include global AMQP constants.
require_once __DIR__ . '/../Amqp/AMQP.php';

AmqpBridge::initialise();
