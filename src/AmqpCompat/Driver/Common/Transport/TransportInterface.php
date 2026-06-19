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

namespace Asmblah\PhpAmqpCompat\Driver\Common\Transport;

use AMQPException;
use Asmblah\PhpAmqpCompat\Driver\Common\Channel\ChannelInterface;
use Asmblah\PhpAmqpCompat\Exception\HeartbeatMissedException;
use Asmblah\PhpAmqpCompat\Exception\TransportConfigurationFailedException;

/**
 * Interface TransportInterface.
 *
 * Manages the connection with the underlying driver.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface TransportInterface
{
    /**
     * Ensures that the connection is open and no heartbeats have been missed.
     *
     * @throws HeartbeatMissedException
     */
    public function checkHeartbeat(): void;

    /**
     * Disconnects from the AMQP broker server.
     *
     * @param class-string<AMQPException> $exceptionClass
     */
    public function disconnect(string $exceptionClass, string $methodName): void;

    /**
     * Fetches the interval at which heartbeats should be sent.
     */
    public function getHeartbeatInterval(): int;

    /**
     * Fetches whether the connection is busy, e.g. mid-write.
     */
    public function isBusy(): bool;

    /**
     * Checks whether the connection is open.
     */
    public function isConnected(): bool;

    /**
     * Opens a channel on the underlying connection.
     *
     * @param class-string<AMQPException> $exceptionClass
     * @throws AMQPException
     */
    public function openChannel(string $exceptionClass, string $methodName): ChannelInterface;

    /**
     * Updates the read timeout for the connection.
     * Will reconfigure the open connection if one is already established.
     *
     * @throws TransportConfigurationFailedException If the socket read timeout change fails.
     */
    public function setReadTimeout(float $seconds): void;
}
