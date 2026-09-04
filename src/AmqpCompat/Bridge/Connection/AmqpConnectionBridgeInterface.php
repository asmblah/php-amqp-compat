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

use AMQPException;
use Asmblah\PhpAmqpCompat\Bridge\AmqpBridgeResourceInterface;
use Asmblah\PhpAmqpCompat\Bridge\Channel\AmqpChannelBridgeInterface;
use Asmblah\PhpAmqpCompat\Connection\Config\ConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Transport\TransportInterface;
use Asmblah\PhpAmqpCompat\Exception\HeartbeatMissedException;
use Asmblah\PhpAmqpCompat\Exception\TooManyChannelsOnConnectionException;
use Asmblah\PhpAmqpCompat\Exception\TransportConfigurationFailedException;

/**
 * Interface AmqpConnectionBridgeInterface.
 *
 * Defines the internal representation of an AMQP connection for this library.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface AmqpConnectionBridgeInterface extends AmqpBridgeResourceInterface
{
    /**
     * Checks whether a client heartbeat needs to be sent or a server heartbeat has been missed.
     *
     * @throws HeartbeatMissedException
     */
    public function checkHeartbeat(): void;

    /**
     * Creates an AmqpChannelBridge for the given connection.
     *
     * @param class-string<AMQPException> $exceptionClass
     * @throws TooManyChannelsOnConnectionException When PHP_AMQP_MAX_CHANNELS would be exceeded.
     * @throws AMQPException
     */
    public function createChannelBridge(string $exceptionClass, string $methodName): AmqpChannelBridgeInterface;

    /**
     * Disconnects from the AMQP broker server.
     *
     * @param class-string<AMQPException> $exceptionClass
     * @throws AMQPException
     */
    public function disconnect(string $exceptionClass, string $methodName): void;

    /**
     * Fetches the connection configuration.
     */
    public function getConnectionConfig(): ConnectionConfigInterface;

    /**
     * Fetches the configured interval between heartbeats,
     * which will actually be half of "amqp.heartbeat" if set.
     */
    public function getHeartbeatInterval(): int;

    /**
     * Fetches the transport (driver-level connection).
     */
    public function getTransport(): TransportInterface;

    /**
     * Fetches the number of channels currently in use on this connection.
     */
    public function getUsedChannels(): int;

    /**
     * Fetches whether the connection is busy, e.g. mid-write.
     */
    public function isBusy(): bool;

    /**
     * Determines whether the connection is open.
     */
    public function isConnected(): bool;

    /**
     * Updates the read timeout for the connection.
     * Will reconfigure the open connection if one is already established.
     *
     * @throws TransportConfigurationFailedException If the read timeout change fails.
     */
    public function setReadTimeout(float $seconds): void;

    /**
     * Unregisters the given AmqpChannelBridge.
     */
    public function unregisterChannelBridge(AmqpChannelBridgeInterface $channelBridge): void;
}
