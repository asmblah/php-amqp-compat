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

use Asmblah\PhpAmqpCompat\Bridge\Channel\AmqpChannelBridge;
use Asmblah\PhpAmqpCompat\Bridge\Channel\AmqpChannelBridgeInterface;
use Asmblah\PhpAmqpCompat\Bridge\Channel\Consumer;
use PhpAmqpLib\Connection\AbstractConnection as AmqplibConnection;

class AmqpConnectionBridge implements AmqpConnectionBridgeInterface
{
    public function __construct(
        private readonly AmqplibConnection $amqplibConnection
    ) {
    }

    /**
     * @inheritDoc
     */
    public function createChannelBridge(
        AmqpConnectionBridgeInterface $connectionBridge
    ): AmqpChannelBridgeInterface {
        $amqplibChannel = $connectionBridge->getAmqplibConnection()->channel();

        return new AmqpChannelBridge($connectionBridge, $amqplibChannel, new Consumer());
    }

    /**
     * @inheritDoc
     */
    public function getAmqplibConnection(): AmqplibConnection
    {
        return $this->amqplibConnection;
    }

    /**
     * @inheritDoc
     */
    public function getHeartbeatInterval(): int
    {
        $timeout = $this->amqplibConnection->getHeartbeat();

        return (int)ceil($timeout / 2);
    }
}
