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

namespace Asmblah\PhpAmqpCompat\Bridge;

use AMQPChannel;
use AMQPConnection;
use Asmblah\PhpAmqpCompat\Bridge\Channel\AmqpChannelBridgeInterface;
use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Connection\Config\ConnectionConfigInterface;
use WeakMap;

/**
 * Manages bridging between the AMQP* classes that emulate the php-amqp library API,
 * where classes are instantiated by userland, and this library's own internal logic.
 */
class AmqpBridge
{
    /**
     * @var WeakMap<AMQPChannel, AmqpChannelBridgeInterface>
     */
    private static WeakMap $amqpChannelBridgeMap;
    /**
     * @var WeakMap<AMQPConnection, AmqpConnectionBridgeInterface>
     */
    private static WeakMap $amqpConnectionBridgeMap;
    /**
     * @var WeakMap<AMQPConnection, ConnectionConfigInterface>
     */
    private static WeakMap $connectionConfigMap;
    private static bool $initialised = false;

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
     * Installs the corresponding ConnectionConfig for a given AMQPConnection.
     */
    public static function bridgeConnectionConfig(
        AMQPConnection $amqpConnection,
        ConnectionConfigInterface $connectionConfig
    ): void {
        self::$connectionConfigMap[$amqpConnection] = $connectionConfig;
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
     * Fetches the corresponding ConnectionConfig for a given AMQPConnection.
     */
    public static function getConnectionConfig(AMQPConnection $amqpConnection): ConnectionConfigInterface
    {
        return self::$connectionConfigMap[$amqpConnection];
    }

    /**
     * Called by bootstrap.php.
     */
    public static function initialise(): void
    {
        if (self::$initialised) {
            return; // Already initialised.
        }

        self::initialiseMaps();

        self::$initialised = true;
    }

    /**
     * Determines whether initialisation of the bridge has been performed yet.
     */
    public static function isInitialised(): bool
    {
        return self::$initialised;
    }

    private static function initialiseMaps(): void
    {
        /** @var WeakMap<AMQPChannel, AmqpChannelBridgeInterface> $amqpChannelBridgeMap */
        $amqpChannelBridgeMap = new WeakMap();
        /** @var WeakMap<AMQPConnection, AmqpConnectionBridgeInterface> $amqpConnectionBridgeMap */
        $amqpConnectionBridgeMap = new WeakMap();
        /** @var WeakMap<AMQPConnection, ConnectionConfigInterface> $connectionConfigMap */
        $connectionConfigMap = new WeakMap();

        self::$amqpChannelBridgeMap = $amqpChannelBridgeMap;
        self::$amqpConnectionBridgeMap = $amqpConnectionBridgeMap;
        self::$connectionConfigMap = $connectionConfigMap;
    }

    /**
     * Uninitialises the bridge.
     */
    public static function uninitialise(): void
    {
        self::initialiseMaps();

        self::$initialised = false;
    }
}
