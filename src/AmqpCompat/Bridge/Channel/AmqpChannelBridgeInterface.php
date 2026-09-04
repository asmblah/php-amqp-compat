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

namespace Asmblah\PhpAmqpCompat\Bridge\Channel;

use AMQPEnvelope;
use AMQPException;
use AMQPQueue;
use Asmblah\PhpAmqpCompat\Bridge\AmqpBridgeResourceInterface;
use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Channel\ChannelInterface;
use Asmblah\PhpAmqpCompat\Exception\StopConsumptionException;

/**
 * Interface AmqpChannelBridgeInterface.
 *
 * Defines the internal representation of an AMQP channel for this library.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface AmqpChannelBridgeInterface extends AmqpBridgeResourceInterface
{
    /**
     * Acquires the internal driver-level channel, ensuring it is connected.
     *
     * @throws AMQPException
     */
    public function acquireChannel(string $errorOnFailure): ChannelInterface;

    /**
     * Closes the channel without raising an AMQP exception if it is already closed or in a broken state.
     */
    public function closeQuietly(): void;

    /**
     * Consumes the given envelope, raising a StopConsumptionException
     * if further consumption should be stopped.
     *
     * @throws StopConsumptionException
     */
    public function consumeEnvelope(AMQPEnvelope $amqpEnvelope): void;

    /**
     * Fetches the channel ID if open, or null if closed.
     */
    public function getChannelId(): ?int;

    /**
     * Fetches the AMQP connection bridge.
     */
    public function getConnectionBridge(): AmqpConnectionBridgeInterface;

    /**
     * Fetches the callback to use for consuming AMQP messages, if any.
     */
    public function getConsumptionCallback(): callable;

    /**
     * Fetches the configured read timeout for the connection.
     */
    public function getReadTimeout(): float;

    /**
     * Fetches all subscribed consumers as a map from consumer tag to AMQPQueue.
     *
     * @return array<string, AMQPQueue>
     */
    public function getSubscribedConsumers(): array;

    /**
     * Determines whether the underlying connection is open.
     */
    public function isConnected(): bool;

    /**
     * Determines whether a consumer with the given tag is subscribed.
     */
    public function isConsumerSubscribed(string $consumerTag): bool;

    /**
     * Determines whether the channel is open.
     */
    public function isOpen(): bool;

    /**
     * Sets the callback to use for consuming AMQP messages.
     */
    public function setConsumptionCallback(callable $callback): void;

    /**
     * Subscribes the given consumer.
     */
    public function subscribeConsumer(string $consumerTag, AMQPQueue $amqpQueue): void;

    /**
     * Unregisters this channel from its connection.
     */
    public function unregisterChannel(): void;

    /**
     * Unsubscribes the given consumer.
     */
    public function unsubscribeConsumer(string $consumerTag): void;
}
