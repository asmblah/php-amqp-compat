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

namespace Asmblah\PhpAmqpCompat\Driver\Common\Channel;

use AMQPChannelException;
use AMQPConnectionException;
use AMQPEnvelope;
use AMQPException;
use Asmblah\PhpAmqpCompat\Bridge\Channel\Envelope;

/**
 * Interface ChannelInterface.
 *
 * Provides the driver-level channel abstraction.
 *
 * @phpstan-import-type EnvelopeAttributes from Envelope
 * @author Dan Phillimore <dan@ovms.co>
 */
interface ChannelInterface
{
    /**
     * Acknowledges one or more messages as successfully handled.
     *
     * @param class-string<AMQPException> $exceptionClass
     *
     * @throws AMQPChannelException If the channel is not open.
     * @throws AMQPConnectionException If the connection to the broker was lost.
     * @throws AMQPException
     */
    public function basicAck(int $deliveryTag, bool $multiple, string $exceptionClass, string $methodName): void;

    /**
     * Cancels the subscription of a consumer to a queue it is consuming from.
     *
     * @param class-string<AMQPException> $exceptionClass
     * @throws AMQPException
     */
    public function basicCancel(
        string $consumerTag,
        bool $noWait,
        string $exceptionClass,
        string $methodName
    ): void;

    /**
     * Starts consuming from all subscribed queues, returning the consumer tag.
     *
     * @param callable(AMQPEnvelope): void $callback
     * @param class-string<AMQPException> $exceptionClass
     * @return string Returns the consumer tag.
     * @throws AMQPException
     */
    public function basicConsume(
        string $queueName,
        string $consumerTag,
        bool $noLocal,
        bool $autoAck,
        bool $exclusive,
        callable $callback,
        string $exceptionClass,
        string $methodName
    ): string;

    /**
     * Fetches a message directly from a queue, returning null if the queue is empty.
     *
     * @param class-string<AMQPException> $exceptionClass
     * @return AMQPEnvelope|null
     * @throws AMQPException
     */
    public function basicGet(
        string $queueName,
        bool $autoAck,
        string $exceptionClass,
        string $methodName
    ): ?AMQPEnvelope;

    /**
     * Negatively-acknowledges a message.
     *
     * @param class-string<AMQPException> $exceptionClass
     * @throws AMQPException
     */
    public function basicNack(
        int $deliveryTag,
        bool $multiple,
        bool $requeue,
        string $exceptionClass,
        string $methodName
    ): void;

    /**
     * Publishes a message on an exchange.
     *
     * @param EnvelopeAttributes $attributes
     * @param class-string<AMQPException> $exceptionClass
     * @throws AMQPException
     */
    public function basicPublish(
        string $message,
        array $attributes,
        string $exchangeName,
        string $routingKey,
        bool $mandatory,
        bool $immediate,
        string $exceptionClass,
        string $methodName
    ): void;

    /**
     * Sets the Quality Of Service settings for this channel, either for the current consumer or globally.
     *
     * @param bool $global True to change the settings globally,
     *                     false to only change them for the current consumer.
     * @param class-string<AMQPException> $exceptionClass
     * @throws AMQPException
     */
    public function basicQos(
        int $size,
        int $count,
        bool $global,
        string $exceptionClass,
        string $methodName
    ): void;

    /**
     * Redelivers unacknowledged messages to consumers.
     *
     * @param class-string<AMQPException> $exceptionClass
     * @throws AMQPException
     */
    public function basicRecover(
        bool $requeue,
        string $exceptionClass,
        string $methodName
    ): void;

    /**
     * Marks a single message as explicitly not acknowledged (vs. negatively acknowledged).
     *
     * @param class-string<AMQPException> $exceptionClass
     * @throws AMQPException
     */
    public function basicReject(
        int $deliveryTag,
        bool $requeue,
        string $exceptionClass,
        string $methodName
    ): void;

    /**
     * Binds the specified destination exchange to a source exchange.
     *
     * @param array<string, scalar> $arguments
     * @param class-string<AMQPException> $exceptionClass
     * @throws AMQPException
     */
    public function bindExchange(
        string $destinationExchangeName,
        string $sourceExchangeName,
        string $routingKey,
        bool $noWait,
        array $arguments,
        string $exceptionClass,
        string $methodName
    ): void;

    /**
     * Binds a queue to an exchange on a given routing key.
     *
     * @param array<string, scalar> $arguments
     * @param class-string<AMQPException> $exceptionClass
     *
     * @throws AMQPChannelException If the channel is not open.
     * @throws AMQPConnectionException If the connection to the broker was lost.
     * @throws AMQPException
     */
    public function bindQueue(
        string $queueName,
        string $exchangeName,
        string $routingKey,
        array $arguments,
        string $exceptionClass,
        string $methodName
    ): void;

    /**
     * Closes the channel without raising an AMQP exception if it is already closed or in a broken state.
     */
    public function closeQuietly(): void;

    /**
     * Commits the current AMQP transaction.
     *
     * @param class-string<AMQPException> $exceptionClass
     * @throws AMQPException
     */
    public function commitTransaction(string $exceptionClass, string $methodName): void;

    /**
     * Declares an exchange on the broker.
     *
     * Idempotent - creates the exchange if it does not already exist,
     * otherwise verifies that the existing one matches the given configuration.
     *
     * @param array<string, scalar> $arguments
     * @param class-string<AMQPException> $exceptionClass
     * @throws AMQPException
     */
    public function declareExchange(
        string $exchangeName,
        string $exchangeType,
        bool $passive,
        bool $durable,
        bool $autoDelete,
        bool $internal,
        bool $noWait,
        array $arguments,
        string $exceptionClass,
        string $methodName
    ): void;

    /**
     * Declares a queue, creating it on the broker if needed.
     *
     * @param array<string, scalar> $arguments
     * @param class-string<AMQPException> $exceptionClass
     * @return array{name: string, count: int} The queue name (which may have been generated
     *                                         if $queueName was the empty string) and the message count for the queue,
     *                                         which could be non-zero if it already existed.
     * @throws AMQPException
     */
    public function declareQueue(
        string $queueName,
        bool $passive,
        bool $durable,
        bool $exclusive,
        bool $autoDelete,
        bool $noWait,
        array $arguments,
        string $exceptionClass,
        string $methodName
    ): array;

    /**
     * Deletes an exchange from the broker.
     *
     * @param class-string<AMQPException> $exceptionClass
     * @throws AMQPException
     */
    public function deleteExchange(
        string $exchangeName,
        bool $ifUnused,
        bool $noWait,
        string $exceptionClass,
        string $methodName
    ): void;

    /**
     * Deletes a queue from the broker, returning the number of deleted messages.
     *
     * @param class-string<AMQPException> $exceptionClass
     * @return int The number of deleted messages that were on the queue.
     * @throws AMQPException
     */
    public function deleteQueue(
        string $queueName,
        bool $ifUnused,
        bool $ifEmpty,
        bool $noWait,
        string $exceptionClass,
        string $methodName
    ): int;

    /**
     * Fetches the channel ID if open, or null if closed.
     */
    public function getChannelId(): ?int;

    /**
     * Determines whether this channel has a corresponding connection.
     */
    public function hasConnection(): bool;

    /**
     * Determines whether this channel's connection is open.
     */
    public function isConnected(): bool;

    /**
     * Determines whether this channel is open on its connection.
     */
    public function isOpen(): bool;

    /**
     * Deletes all messages from a queue.
     *
     * @param class-string<AMQPException> $exceptionClass
     * @throws AMQPException
     */
    public function purgeQueue(
        string $queueName,
        bool $noWait,
        string $exceptionClass,
        string $methodName
    ): void;

    /**
     * Rolls back the active AMQP transaction.
     *
     * @param class-string<AMQPException> $exceptionClass
     * @throws AMQPException
     */
    public function rollbackTransaction(string $exceptionClass, string $methodName): void;

    /**
     * Starts an AMQP transaction.
     *
     * @param class-string<AMQPException> $exceptionClass
     * @throws AMQPException
     */
    public function startTransaction(string $exceptionClass, string $methodName): void;

    /**
     * Unbinds the specified destination exchange from a source exchange.
     *
     * @param array<string, scalar> $arguments
     * @param class-string<AMQPException> $exceptionClass
     * @throws AMQPException
     */
    public function unbindExchange(
        string $destinationExchangeName,
        string $sourceExchangeName,
        string $routingKey,
        bool $noWait,
        array $arguments,
        string $exceptionClass,
        string $methodName
    ): void;

    /**
     * Surrenders control to the driver to allow it to process AMQP frames up to the given timeout.
     * For example, waiting for messages to consume.
     *
     * @param float|int $timeout Timeout to wait for an AMQP frame to process in seconds.
     * @param class-string<AMQPException> $exceptionClass
     * @throws AMQPException
     */
    public function wait(float|int $timeout, string $exceptionClass, string $methodName): void;
}
