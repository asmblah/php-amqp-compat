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

namespace Asmblah\PhpAmqpCompat\Bridge;

use AMQPChannel;
use AMQPConnection;
use Asmblah\PhpAmqpCompat\Bridge\Channel\AmqpChannelBridgeInterface;
use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use WeakMap;

/**
 * Manages bridging between the AMQP* classes that emulate the php-amqp library API,
 * where classes are instantiated by userland, and this library's own internal logic.
 */
class AmqpBridge
{
    private static WeakMap $amqpChannelBridgeMap;

    private static WeakMap $amqpConnectionBridgeMap;

    /**
     * Installs the corresponding AmqpChannelBridge for a given AMQPChannel.
     */
    public static function bridgeChannel(
        AMQPChannel $amqpChannel,
        AmqpChannelBridgeInterface $amqpChannelBridge
    ): void {
        self::$amqpChannelBridgeMap[$amqpChannel] = $amqpChannelBridge;
    }

    /**
     * Installs the corresponding AmqpConnectionBridge for a given AMQPConnection.
     */
    public static function bridgeConnection(
        AMQPConnection $amqpConnection,
        AmqpConnectionBridgeInterface $amqpConnectionBridge
    ): void {
        self::$amqpConnectionBridgeMap[$amqpConnection] = $amqpConnectionBridge;
    }

    /**
     * Fetches the corresponding AmqpChannelBridge for a given AMQPChannel.
     */
    public static function getBridgeChannel(AMQPChannel $amqpChannel): AmqpChannelBridgeInterface
    {
        return self::$amqpChannelBridgeMap[$amqpChannel];
    }

    /**
     * Fetches the corresponding AmqpConnectionBridge for a given AMQPConnection.
     */
    public static function getBridgeConnection(AMQPConnection $amqpConnection): AmqpConnectionBridgeInterface
    {
        return self::$amqpConnectionBridgeMap[$amqpConnection];
    }

    /**
     * Called by bootstrap.php.
     */
    public static function initialise(): void
    {
        self::$amqpChannelBridgeMap = new WeakMap();
        self::$amqpConnectionBridgeMap = new WeakMap();
    }
}
