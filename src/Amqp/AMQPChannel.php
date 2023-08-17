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
use PhpAmqpLib\Channel\AMQPChannel as AmqplibChannel;
use PhpAmqpLib\Connection\AbstractConnection as AmqplibConnection;
use PhpAmqpLib\Exception\AMQPExceptionInterface;

/**
 * Class AMQPChannel.
 *
 * Emulates AMQPChannel from pecl-amqp.
 *
 * @see {@link https://github.com/php-amqp/php-amqp/blob/v1.11.0/stubs/AMQPChannel.php}
 */
class AMQPChannel
{
    private readonly AmqplibChannel $amqplibChannel;
    private readonly AmqplibConnection $amqplibConnection;
    private readonly AmqpChannelBridgeInterface $channelBridge;

    /**
     * @param AmqpConnection $amqpConnection An instance of AMQPConnection
     *                       with an active connection to a
     *                       broker.
     *
     * @throws AMQPConnectionException If the connection to the broker
     *                                 was lost.
     */
    public function __construct(private readonly AmqpConnection $amqpConnection)
    {
        $connectionBridge = AmqpBridge::getBridgeConnection($amqpConnection);
        $this->amqplibConnection = $connectionBridge->getAmqplibConnection();

        $this->channelBridge = $connectionBridge->createChannelBridge();
        AmqpBridge::bridgeChannel($this, $this->channelBridge);

        $this->amqplibChannel = $this->channelBridge->getAmqplibChannel();
    }

    public function __destruct()
    {
        // Match the behaviour of php-amqp/ext-amqp: on destruction, close the channel.
        if ($this->amqplibChannel->is_open()) {
            $this->amqplibChannel->close();
        }

        // Ensure we unregister the channel so that e.g. AMQPConnection->getUsedChannels() returns the correct value.
        $this->channelBridge->unregisterChannel();
    }

    /**
     * Redelivers unacknowledged messages.
     *
     * @throws AMQPChannelException
     * @throws AMQPConnectionException
     */
    public function basicRecover(bool $requeue = true): bool
    {
        $this->checkChannelOrThrow('Could not redeliver unacknowledged messages.');

        try {
            $this->amqplibChannel->basic_recover($requeue);
        } catch (AMQPExceptionInterface $exception) {
            // TODO: Handle errors identically to php-amqp.
            throw new AMQPChannelException(__METHOD__ . ' failed: ' . $exception->getMessage());
        }

        return true;
    }

    /**
     * Ensures the channel is usable or bails out if not.
     *
     * @throws AMQPChannelException
     * @throws AMQPConnectionException
     */
    private function checkChannelOrThrow(string $error): void
    {
        if (!$this->amqplibChannel->getConnection()) {
            throw new AMQPChannelException($error . ' No channel available.');
        }

        if (!$this->amqplibConnection->isConnected()) {
            throw new AMQPConnectionException($error . 'No connection available.');
        }
    }

    /**
     * Closes the channel.
     */
    public function close(): void
    {
        $this->amqplibChannel->close();
    }

    /**
     * Commits a pending transaction.
     *
     * @throws AMQPChannelException    If no transaction was started prior to
     *                                 calling this method.
     * @throws AMQPConnectionException If the connection to the broker was lost.
     *
     * @return bool TRUE on success or FALSE on failure.
     */
    public function commitTransaction(): bool
    {
        $this->checkChannelOrThrow('Could not commit the transaction.');

        try {
            $this->amqplibChannel->tx_commit();
        } catch (AMQPExceptionInterface $exception) {
            // TODO: Handle errors identically to php-amqp.
            throw new AMQPChannelException(__METHOD__ . ' failed: ' . $exception->getMessage());
        }

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
        return $this->amqplibChannel->getChannelId();
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
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Fetches the number of messages to prefetch from the broker across all consumers.
     */
    public function getGlobalPrefetchCount(): int
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Fetches the window size to prefetch from the broker for all consumers.
     */
    public function getGlobalPrefetchSize(): int
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Fetches the number of messages to prefetch from the broker for each consumer.
     */
    public function getPrefetchCount(): int
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Fetches the window size to prefetch from the broker for each consumer.
     */
    public function getPrefetchSize(): int
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Checks the channel connection.
     *
     * @return bool Indicates whether the channel is connected.
     */
    public function isConnected(): bool
    {
        return $this->amqplibChannel->getConnection()->isConnected();
    }

    /**
     * Sets the Quality Of Service settings for the given channel.
     *
     * Specify the amount of data to prefetch in terms of window size (octets)
     * or number of messages from a queue during a AMQPQueue::consume() or
     * AMQPQueue::get() method call. The client will prefetch data up to size
     * octets or count messages from the server, whichever limit is hit first.
     * Setting either value to 0 will instruct the client to ignore that
     * particular setting. A call to AMQPChannel::qos() will overwrite any
     * values set by calling AMQPChannel::setPrefetchSize() and
     * AMQPChannel::setPrefetchCount(). If the call to either
     * AMQPQueue::consume() or AMQPQueue::get() is done with the AMQP_AUTOACK
     * flag set, the client will not do any prefetching of data, regardless of
     * the QOS settings.
     *
     * @param integer $size   The window size, in octets, to prefetch.
     * @param integer $count  The number of messages to prefetch.
     * @param bool    $global TRUE for global, FALSE for consumer. FALSE by default.
     *
     * @throws AMQPChannelException If the connection to the broker was lost.
     *
     * @return bool TRUE on success or FALSE on failure.
     */
    public function qos(int $size, int $count, bool $global): bool
    {
        $this->checkChannelOrThrow('Could not set qos parameters.');

        try {
            $this->amqplibChannel->basic_qos($size, $count, $global);
        } catch (AMQPExceptionInterface $exception) {
            // TODO: Handle errors identically to php-amqp.
            throw new AMQPChannelException(__METHOD__ . ' failed: ' . $exception->getMessage());
        }

        return true;
    }

    /**
     * Rolls back a transaction.
     *
     * ::startTransaction() must be called prior to this.
     *
     * @throws AMQPChannelException    If no transaction was started prior to
     *                                 calling this method.
     * @throws AMQPConnectionException If the connection to the broker was lost.
     *
     * @return bool TRUE on success or FALSE on failure.
     */
    public function rollbackTransaction(): bool
    {
        $this->checkChannelOrThrow('Could not rollback the transaction.');

        try {
            $this->amqplibChannel->tx_rollback();
        } catch (AMQPExceptionInterface $exception) {
            // TODO: Handle errors identically to php-amqp.
            throw new AMQPChannelException(__METHOD__ . ' failed: ' . $exception->getMessage());
        }

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
     *
     * @return boolean TRUE on success or FALSE on failure.
     */
    public function setGlobalPrefetchCount(int $count): bool
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
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
     *
     * @return bool TRUE on success or FALSE on failure.
     */
    public function setGlobalPrefetchSize(int $size): bool
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
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
     *
     * @return boolean TRUE on success or FALSE on failure.
     */
    public function setPrefetchCount(int $count): bool
    {
        $this->checkChannelOrThrow('Could not set prefetch count.');

        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
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
     *
     * @return bool TRUE on success or FALSE on failure.
     */
    public function setPrefetchSize(int $size): bool
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }

    /**
     * Sets the callback to process basic.return AMQP server method
     *
     * @param callable|null $return_callback
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
    public function setReturnCallback(callable $return_callback=null)
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
     */
    public function startTransaction(): bool
    {
        $this->checkChannelOrThrow('Could not start the transaction.');

        try {
            $this->amqplibChannel->tx_select();
        } catch (AMQPExceptionInterface $exception) {
            // TODO: Handle errors identically to php-amqp.
            throw new AMQPChannelException(__METHOD__ . ' failed: ' . $exception->getMessage());
        }

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
