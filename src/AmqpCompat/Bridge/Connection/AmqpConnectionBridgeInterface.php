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

namespace Asmblah\PhpAmqpCompat\Bridge\Connection;

use Asmblah\PhpAmqpCompat\Bridge\Channel\AmqpChannelBridgeInterface;
use PhpAmqpLib\Connection\AbstractConnection as AmqplibConnection;

interface AmqpConnectionBridgeInterface
{
    /**
     * Creates an AmqpChannelBridge for the given connection.
     */
    public function createChannelBridge(): AmqpChannelBridgeInterface;

    /**
     * Fetches the internal php-amqplib connection.
     */
    public function getAmqplibConnection(): AmqplibConnection;

    /**
     * Fetches the configured interval between heartbeats,
     * which will actually be half of "amqp.heartbeat" if set.
     */
    public function getHeartbeatInterval(): int;

    /**
     * Fetches the number of channels currently in use on this connection.
     */
    public function getUsedChannels(): int;

    /**
     * Unregisters the given AmqpChannelBridge.
     */
    public function unregisterChannelBridge(AmqpChannelBridgeInterface $channelBridge): void;
}
