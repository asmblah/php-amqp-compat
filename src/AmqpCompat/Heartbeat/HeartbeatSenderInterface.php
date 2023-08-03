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

namespace Asmblah\PhpAmqpCompat\Heartbeat;

use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;

interface HeartbeatSenderInterface
{
    /**
     * Installs heartbeat handling for the given AMQP connection.
     */
    public function register(AmqpConnectionBridgeInterface $connectionBridge): void;

    /**
     * Removes heartbeat handling for the given AMQP connection.
     */
    public function unregister(AmqpConnectionBridgeInterface $connectionBridge): void;
}
