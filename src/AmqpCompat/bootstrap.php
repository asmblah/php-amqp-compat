<?php

/*
 * PHP AMQP-Compat - php-amqp/ext-amqp compatibility.
 * Copyright (c) Dan Phillimore (asmblah)
 * https://github.com/asmblah/php-amqp-compat/
 *
 * Released under the MIT license.
 * https://github.com/asmblah/php-amqp-compat/raw/master/MIT-LICENSE.txt
 */

declare(strict_types=1);

use Asmblah\PhpAmqpCompat\Bridge\AmqpBridge;

if (extension_loaded('amqp') && !defined('PHP_AMQP_COMPAT')) {
    trigger_error(
        'ext-amqp must be uninstalled to use php-amqp-compat, ext-amqp will still be used',
        E_USER_WARNING
    );

    return;
}

// Include global AMQP constants.
require_once __DIR__ . '/../Amqp/AMQP.php';

AmqpBridge::initialise();
