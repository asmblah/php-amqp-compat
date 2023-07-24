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

namespace Asmblah\PhpAmqpCompat\Bridge\Connection;

use Asmblah\PhpAmqpCompat\Bridge\Channel\AmqpChannelBridgeInterface;
use PhpAmqpLib\Connection\AbstractConnection as AmqplibConnection;

interface AmqpConnectionBridgeInterface
{
    /**
     * Creates an AmqpChannelBridge for the given connection.
     */
    public function createChannelBridge(
        AmqpConnectionBridgeInterface $connectionBridge
    ): AmqpChannelBridgeInterface;

    /**
     * Fetches the internal php-amqplib connection.
     */
    public function getAmqplibConnection(): AmqplibConnection;

    public function getHeartbeatInterval(): int;
}
