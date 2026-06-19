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

use Asmblah\PhpAmqpCompat\Bridge\AmqpBridge;
use Asmblah\PhpAmqpCompat\Bridge\Channel\AmqpChannelBridgeInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Channel\ChannelInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Logger\LoggerInterface;
use Asmblah\PhpAmqpCompat\Exception\TooManyChannelsOnConnectionException;

/**
 * Class AMQPChannel.
 *
 * Emulates AMQPChannel from pecl-amqp.
 *
 * @see {@link https://github.com/php-amqp/php-amqp/blob/v1.11.0/stubs/AMQPChannel.php}
 */
class AMQPChannel
{
    private readonly AmqpChannelBridgeInterface $channelBridge;
    /**
     * The implementation allows for extension,
     * where the parent constructor may not be called.
     */
    private bool $constructorCalled = false;
    /**
     * Number of messages to prefetch in total across all consumers on the channel.
     *
     * Initialised to 0 to handle the supported edge-case where the constructor is not called.
     */
    private int $globalPrefetchCount = 0;
    /**
     * Maximum amount of content (measured in octets) to prefetch in total across all consumers on the channel.
     *
     * Initialised to 0 to handle the supported edge-case where the constructor is not called.
     */
    private int $globalPrefetchSize = 0;
    private readonly LoggerInterface $logger;
    /**
     * Number of messages to prefetch for each consumer on the channel.
     *
     * Initialised to 0 to handle the supported edge-case where the constructor is not called.
     */
    private int $prefetchCount = 0;
    /**
     * Maximum amount of content (measured in octets) to prefetch for each consumer on the channel.
     *
     * Initialised to 0 to handle the supported edge-case where the constructor is not called.
     */
    private int $prefetchSize = 0;

    /**
     * @param AMQPConnection $amqpConnection An instance of AMQPConnection
     *                       with an active connection to a
     *                       broker.
     *
     * @throws AMQPConnectionException If the connection to the broker
     *                                 was lost.
     * @throws AMQPChannelException If PHP_AMQP_MAX_CHANNELS would be exceeded.
     * @throws AMQPException
     */
    public function __construct(private readonly AMQPConnection $amqpConnection)
    {
        $connectionBridge = AmqpBridge::getBridgeConnection($amqpConnection);
        $this->logger = $connectionBridge->getLogger();

        try {
            $this->channelBridge = $connectionBridge->createChannelBridge(
                AMQPChannelException::class,
                __METHOD__
            );
        } catch (TooManyChannelsOnConnectionException) {
            throw new AMQPChannelException(
                'Could not create channel. Connection has no open channel slots remaining.'
            );
        }

        AmqpBridge::bridgeChannel($this, $this->channelBridge);

        // Always set here in the constructor - however, the API allows for the class to be extended
        // and so this parent constructor may not be called. See reference implementation tests.
        $this->constructorCalled = true;

        $connectionConfig = $connectionBridge->getConnectionConfig();

        // Load channel configuration.
        $this->globalPrefetchCount = $connectionConfig->getGlobalPrefetchCount();
        $this->globalPrefetchSize = $connectionConfig->getGlobalPrefetchSize();
        $this->prefetchCount = $connectionConfig->getPrefetchCount();
        $this->prefetchSize = $connectionConfig->getPrefetchSize();

        $channel = $this->channelBridge->acquireChannel('Could not initialise channel.');

        // Set initial Quality-Of-Service/prefetch settings for the channel.
        $channel->basicQos(
            $this->prefetchSize,
            $this->prefetchCount,
            global: false,
            exceptionClass: AMQPChannelException::class,
            methodName: __METHOD__
        );

        if ($this->globalPrefetchCount !== 0 || $this->globalPrefetchSize !== 0) {
            // Writing consumer prefetch settings will override global ones - so they must be re-written if set.
            $channel->basicQos(
                $this->globalPrefetchSize,
                $this->globalPrefetchCount,
                global: true,
                exceptionClass: AMQPChannelException::class,
                methodName: __METHOD__
            );
        }
    }

    public function __destruct()
    {
        if (!$this->constructorCalled) {
            // See notes on property and in constructor.
            return;
        }

        $this->channelBridge->closeQuietly();

        // Ensure we unregister the channel so that e.g. AMQPConnection->getUsedChannels() returns the correct value.
        $this->channelBridge->unregisterChannel();
    }

    /**
     * Redelivers unacknowledged messages.
     *
     * @throws AMQPChannelException
     * @throws AMQPConnectionException
     * @throws AMQPException
     */
    public function basicRecover(bool $requeue = true): bool
    {
        $channel = $this->checkChannelOrThrow('Could not redeliver unacknowledged messages.');

        $this->logger->debug(__METHOD__ . '(): Recovery attempt', [
            'requeue' => $requeue,
        ]);

        $channel->basicRecover($requeue, AMQPChannelException::class, __METHOD__);

        $this->logger->debug(__METHOD__ . '(): Recovered');

        return true;
    }

    /**
     * Ensures the channel is usable or bails out if not.
     *
     * @throws AMQPChannelException
     * @throws AMQPConnectionException
     * @throws AMQPException
     */
    private function checkChannelOrThrow(string $error): ChannelInterface
    {
        if (!$this->constructorCalled) {
            throw new AMQPChannelException($error . ' Stale reference to the channel object.');
        }

        return $this->channelBridge->acquireChannel($error);
    }

    /**
     * Closes the channel.
     */
    public function close(): void
    {
        if (!$this->constructorCalled) {
            // We cannot log this separately as without the constructor being called,
            // there will be no logger available.
            throw new LogicException(__METHOD__ . '(): Invalid channel; constructor was never called');
        }

        $this->logger->debug(__METHOD__ . '(): Channel close attempt');

        if (!$this->channelBridge->isOpen()) {
            $this->logger->debug(__METHOD__ . '(): Channel already closed');

            return;
        }

        if (!$this->channelBridge->isConnected()) {
            $this->logger->warning(__METHOD__ . '(): Underlying connection has already been closed');
        }

        // Now that we have ensured that it is open, we can log the channel ID.
        $this->logger->debug(__METHOD__ . '(): Closing channel', [
            'id' => $this->channelBridge->getChannelId(),
        ]);

        $this->channelBridge->closeQuietly();

        $this->logger->debug(__METHOD__ . '(): Channel closed');
    }

    /**
     * Commits a pending transaction.
     *
     * @throws AMQPChannelException    If no transaction was started prior to
     *                                 calling this method.
     * @throws AMQPConnectionException If the connection to the broker was lost.
     * @throws AMQPException
     *
     * @return bool TRUE on success or FALSE on failure.
     */
    public function commitTransaction(): bool
    {
        $channel = $this->checkChannelOrThrow('Could not commit the transaction.');

        $this->logger->debug(__METHOD__ . '(): Transaction commit attempt');

        $channel->commitTransaction(AMQPChannelException::class, __METHOD__);

        $this->logger->debug(__METHOD__ . '(): Transaction committed');

        return true;
    }

    /**
     * Sets the channel to use publisher acknowledgements.
     * This can only be used on a non-transactional channel.
     */
    public function confirmSelect(): void
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Returns the internal channel ID if open, or null if the channel is closed.
     */
    public function getChannelId(): ?int
    {
        return $this->channelBridge->getChannelId();
    }

    /**
     * Fetches the AMQPConnection object in use.
     */
    public function getConnection(): AMQPConnection
    {
        return $this->amqpConnection;
    }

    /**
     * Fetches all current consumers where key is consumer
     * and value is the AMQPQueue the consumer is running on.
     *
     * @return AMQPQueue[]
     */
    public function getConsumers(): array
    {
        return $this->channelBridge->getSubscribedConsumers();
    }

    /**
     * Fetches the number of messages to prefetch from the broker in total across all consumers on the channel.
     */
    public function getGlobalPrefetchCount(): int
    {
        return $this->globalPrefetchCount;
    }

    /**
     * Fetches the maximum amount of content (measured in octets) to prefetch from the broker
     * in total across all consumers on the channel.
     */
    public function getGlobalPrefetchSize(): int
    {
        return $this->globalPrefetchSize;
    }

    /**
     * Fetches the number of messages to prefetch from the broker for each consumer on the channel.
     */
    public function getPrefetchCount(): int
    {
        return $this->prefetchCount;
    }

    /**
     * Fetches the maximum amount of content (measured in octets) to prefetch from the broker
     * for each consumer on the channel.
     */
    public function getPrefetchSize(): int
    {
        return $this->prefetchSize;
    }

    /**
     * Checks the channel connection.
     *
     * @return bool Indicates whether the channel is connected.
     */
    public function isConnected(): bool
    {
        return $this->channelBridge->isConnected();
    }

    /**
     * Sets the Quality Of Service settings for this channel.
     *
     * Specify the amount of data to prefetch in terms of window size (octets)
     * or number of messages from a queue during an `AMQPQueue::consume()` or
     * `AMQPQueue::get()` method call. The client will prefetch data up to `$size`
     * octets or `$count` messages from the server, whichever limit is hit first.
     * Setting either value to 0 will instruct the client to ignore that
     * particular setting. A call to `AMQPChannel::qos()` will overwrite any
     * values set by calling `AMQPChannel::setPrefetchSize()` and
     * `AMQPChannel::setPrefetchCount()`. If the call to either
     * `AMQPQueue::consume()` or `AMQPQueue::get()` is done with the `AMQP_AUTOACK`
     * flag set, the client will not do any prefetching of data, regardless of
     * the QOS settings.
     *
     * @param int $size The window size, in octets, to prefetch.
     * @param int $count The number of messages to prefetch.
     * @param bool $global True to change the settings globally,
     *                     false (default) to only change them for the current consumer.
     *
     * @throws AMQPChannelException If the connection to the broker was lost.
     * @throws AMQPException
     */
    public function qos(int $size, int $count, bool $global = false): bool
    {
        $channel = $this->checkChannelOrThrow('Could not set qos parameters.');

        $this->logger->debug(__METHOD__ . '(): QOS setting change attempt', [
            'count' => $count,
            'global' => $global,
            'size' => $size,
        ]);

        $channel->basicQos($size, $count, $global, AMQPChannelException::class, __METHOD__);

        $this->logger->debug(__METHOD__ . '(): QOS settings changed');

        return true;
    }

    /**
     * Rolls back a transaction.
     *
     * ::startTransaction() must be called prior to this.
     *
     * @throws AMQPChannelException If no transaction was started prior to
     *                              calling this method.
     * @throws AMQPConnectionException If the connection to the broker was lost.
     * @throws AMQPException
     *
     * @return bool TRUE on success or FALSE on failure.
     */
    public function rollbackTransaction(): bool
    {
        $channel = $this->checkChannelOrThrow('Could not rollback the transaction.');

        $this->logger->debug(__METHOD__ . '(): Transaction rollback attempt');

        $channel->rollbackTransaction(AMQPChannelException::class, __METHOD__);

        $this->logger->debug(__METHOD__ . '(): Transaction rolled back');

        return true;
    }

    /**
     * Sets the callback to process basic.ack and basic.nack AMQP server methods
     * (applicable when channel is in confirm mode).
     *
     * @param callable|null $ack_callback
     * @param callable|null $nack_callback
     *
     * Callback functions with all arguments have the following signature:
     *
     *      function ack_callback(int $delivery_tag, bool $multiple) : bool;
     *      function nack_callback(int $delivery_tag, bool $multiple, bool $requeue) : bool;
     *
     * and should return boolean false when wait loop should be canceled.
     *
     * Note, basic.nack server method will only be delivered if an internal error occurs in the Erlang process
     * responsible for a queue (see https://www.rabbitmq.com/confirms.html for details).
     */
    public function setConfirmCallback(?callable $ack_callback = null, ?callable $nack_callback = null): void
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Sets the number of messages to prefetch from the broker across all consumers.
     *
     * Set the number of messages to prefetch from the broker during a call to
     * AMQPQueue::consume() or AMQPQueue::get().
     *
     * @param integer $count The number of messages to prefetch.
     *
     * @throws AMQPConnectionException If the connection to the broker was lost.
     * @throws AMQPException
     *
     * @return boolean TRUE on success or FALSE on failure.
     */
    public function setGlobalPrefetchCount(int $count): bool
    {
        $channel = $this->checkChannelOrThrow('Could not set global prefetch count.');

        $this->logger->debug(__METHOD__ . '(): Global prefetch count change attempt', [
            'count' => $count,
        ]);

        // Size limit is implicitly disabled.
        $channel->basicQos(
            size: 0,
            count: $count,
            global: true,
            exceptionClass: AMQPChannelException::class,
            methodName: __METHOD__
        );

        $this->globalPrefetchCount = $count;
        $this->globalPrefetchSize = 0; // Size limit is implicitly disabled.

        $this->logger->debug(__METHOD__ . '(): Global prefetch count changed');

        return true;
    }

    /**
     * Sets the window size to prefetch from the broker for all consumers.
     *
     * Sets the prefetch window size, in octets, during a call to
     * AMQPQueue::consume() or AMQPQueue::get(). Any call to this method will
     * automatically set the prefetch message count to 0, meaning that the
     * prefetch message count setting will be ignored. If the call to either
     * AMQPQueue::consume() or AMQPQueue::get() is done with the AMQP_AUTOACK
     * flag set, this setting will be ignored.
     *
     * @param integer $size The window size, in octets, to prefetch.
     *
     * @throws AMQPConnectionException If the connection to the broker was lost.
     * @throws AMQPException
     *
     * @return bool TRUE on success or FALSE on failure.
     */
    public function setGlobalPrefetchSize(int $size): bool
    {
        $channel = $this->checkChannelOrThrow('Could not set global prefetch size.');

        $this->logger->debug(__METHOD__ . '(): Global prefetch size change attempt', [
            'size' => $size,
        ]);

        // Count limit is implicitly disabled.
        $channel->basicQos(
            size: $size,
            count: 0,
            global: true,
            exceptionClass: AMQPChannelException::class,
            methodName: __METHOD__
        );

        $this->globalPrefetchSize = $size;
        $this->globalPrefetchCount = 0; // Count limit is implicitly disabled.

        $this->logger->debug(__METHOD__ . '(): Global prefetch size changed');

        return true;
    }

    /**
     * Sets the number of messages to prefetch from the broker for each consumer.
     *
     * Sets the number of messages to prefetch from the broker during a call to
     * AMQPQueue::consume() or AMQPQueue::get().
     *
     * @param integer $count The number of messages to prefetch.
     *
     * @throws AMQPConnectionException If the connection to the broker was lost.
     * @throws AMQPException
     *
     * @return boolean TRUE on success or FALSE on failure.
     */
    public function setPrefetchCount(int $count): bool
    {
        $channel = $this->checkChannelOrThrow('Could not set prefetch count.');

        $this->logger->debug(__METHOD__ . '(): Non-global prefetch count change attempt', [
            'count' => $count,
        ]);

        // Size limit is implicitly disabled when setting count alone.
        $channel->basicQos(
            size: 0,
            count: $count,
            global: false,
            exceptionClass: AMQPChannelException::class,
            methodName: __METHOD__
        );

        if ($this->globalPrefetchCount !== 0 || $this->globalPrefetchSize !== 0) {
            // Writing consumer prefetch settings will override global ones - so they must be re-written if set.
            $channel->basicQos(
                size: $this->globalPrefetchSize,
                count: $this->globalPrefetchCount,
                global: true,
                exceptionClass: AMQPChannelException::class,
                methodName: __METHOD__
            );
        }

        $this->prefetchCount = $count;
        $this->prefetchSize = 0; // Size limit is implicitly disabled.

        $this->logger->debug(__METHOD__ . '(): Non-global prefetch count changed');

        return true;
    }

    /**
     * Sets the window size to prefetch from the broker for each consumer.
     *
     * Sets the prefetch window size, in octets, during a call to
     * AMQPQueue::consume() or AMQPQueue::get(). Any call to this method will
     * automatically set the prefetch message count to 0, meaning that the
     * prefetch message count setting will be ignored. If the call to either
     * AMQPQueue::consume() or AMQPQueue::get() is done with the AMQP_AUTOACK
     * flag set, this setting will be ignored.
     *
     * @param integer $size The window size, in octets, to prefetch.
     *
     * @throws AMQPConnectionException If the connection to the broker was lost.
     * @throws AMQPException
     *
     * @return bool TRUE on success or FALSE on failure.
     */
    public function setPrefetchSize(int $size): bool
    {
        $channel = $this->checkChannelOrThrow('Could not set prefetch size.');

        $this->logger->debug(__METHOD__ . '(): Non-global prefetch size change attempt', [
            'size' => $size,
        ]);

        // Count limit is implicitly disabled when setting size alone.
        $channel->basicQos(
            size: $size,
            count: 0,
            global: false,
            exceptionClass: AMQPChannelException::class,
            methodName: __METHOD__
        );

        if ($this->globalPrefetchCount !== 0 || $this->globalPrefetchSize !== 0) {
            // Writing consumer prefetch settings will override global ones - so they must be re-written if set.
            $channel->basicQos(
                size: $this->globalPrefetchSize,
                count: $this->globalPrefetchCount,
                global: true,
                exceptionClass: AMQPChannelException::class,
                methodName: __METHOD__
            );
        }

        $this->prefetchSize = $size;
        $this->prefetchCount = 0; // Count limit is implicitly disabled.

        $this->logger->debug(__METHOD__ . '(): Non-global prefetch size changed');

        return true;
    }

    /**
     * Sets the callback for processing the `basic.return` AMQP server method.
     *
     * @param callable|null $returnCallback
     *
     * Callback function with all arguments has the following signature:
     *
     *      function callback(int $reply_code,
     *                        string $reply_text,
     *                        string $exchange,
     *                        string $routing_key,
     *                        AMQPBasicProperties $properties,
     *                        string $body) : bool;
     *
     * and should return boolean false when wait loop should be canceled.
     *
     */
    public function setReturnCallback(?callable $returnCallback = null): void
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Starts a transaction.
     *
     * This method must be called on the given channel prior to calling
     * AMQPChannel::commitTransaction() or AMQPChannel::rollbackTransaction().
     *
     * @return bool TRUE on success or FALSE on failure.
     * @throws AMQPChannelException
     * @throws AMQPConnectionException If the connection to the broker was lost.
     * @throws AMQPException
     */
    public function startTransaction(): bool
    {
        $channel = $this->checkChannelOrThrow('Could not start the transaction.');

        $this->logger->debug(__METHOD__ . '(): Transaction start attempt');

        $channel->startTransaction(AMQPChannelException::class, __METHOD__);

        $this->logger->debug(__METHOD__ . '(): Transaction started');

        return true;
    }

    /**
     * Starts the wait loop for basic.return AMQP server methods
     *
     * @param float $timeout Timeout in seconds. May be fractional.
     *
     * @throws AMQPQueueException If timeout occurs.
     */
    public function waitForBasicReturn(float $timeout = 0.0): void
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Waits until all messages published since the last call have been either ack'd or nack'd by the broker.
     *
     * Note, this method also catch all basic.return message from server.
     *
     * @param float $timeout Timeout in seconds. May be fractional.
     *
     * @throws AMQPQueueException If timeout occurs.
     */
    public function waitForConfirm(float $timeout = 0.0): void
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }
}
