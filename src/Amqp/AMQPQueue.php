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
use Asmblah\PhpAmqpCompat\Bridge\Channel\EnvelopeTransformerInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Exception\ExceptionHandlerInterface;
use Asmblah\PhpAmqpCompat\Exception\StopConsumptionException;
use Asmblah\PhpAmqpCompat\Logger\LoggerInterface;
use PhpAmqpLib\Channel\AMQPChannel as AmqplibChannel;
use PhpAmqpLib\Exception\AMQPExceptionInterface;
use PhpAmqpLib\Message\AMQPMessage as AmqplibMessage;
use PhpAmqpLib\Wire\AMQPTable as AmqplibTable;

/**
 * Class AMQPQueue.
 *
 * Emulates AMQPQueue from pecl-amqp.
 *
 * @see {@link https://github.com/php-amqp/php-amqp/blob/v1.11.0/stubs/AMQPQueue.php}
 */
class AMQPQueue
{
    /**
     * Nullable because the implementation allows for extension,
     * where the parent constructor may not be called.
     */
    private ?AmqplibChannel $amqplibChannel = null;
    /**
     * @var array<string, scalar>
     */
    private $arguments = [];
    private bool $autoDelete = true; // By default, the auto_delete flag should be set.
    private readonly AmqpChannelBridgeInterface $channelBridge;
    private bool $durable = false;
    private readonly EnvelopeTransformerInterface $envelopeTransformer;
    private readonly ExceptionHandlerInterface $exceptionHandler;
    private bool $exclusive = false;
    private ?string $lastConsumerTag = null;
    private readonly LoggerInterface $logger;
    private bool $noWait = false;
    private bool $passive = false;
    private string $queueName = '';

    /**
     * @throws AMQPQueueException When amqpChannel is not connected to a
     *                            broker.
     * @throws AMQPConnectionException If the connection to the broker was lost.
     */
    public function __construct(private readonly AMQPChannel $amqpChannel)
    {
        $this->channelBridge = AmqpBridge::getBridgeChannel($this->amqpChannel);
        $this->exceptionHandler = $this->channelBridge->getExceptionHandler();

        // Always set here in the constructor, however the API allows for the class to be extended
        // and so this parent constructor may not be called. See reference implementation tests.
        $this->amqplibChannel = $this->channelBridge->getAmqplibChannel();

        $this->envelopeTransformer = $this->channelBridge->getEnvelopeTransformer();
        $this->logger = $this->channelBridge->getLogger();
    }

    /**
     * Acknowledges the receipt of a message.
     *
     * This method allows the acknowledgement of a message that is retrieved
     * without the AMQP_AUTOACK flag through AMQPQueue::get() or
     * AMQPQueue::consume().
     *
     * @param int $deliveryTag The message delivery tag of which to
     *                         acknowledge receipt.
     * @param int $flags The only valid flag that can be passed is
     *                   AMQP_MULTIPLE.
     *
     * @throws AMQPChannelException If the channel is not open.
     * @throws AMQPConnectionException If the connection to the broker was lost.
     */
    public function ack(int $deliveryTag, int $flags = AMQP_NOPARAM): bool
    {
        $amqplibChannel = $this->checkChannelOrThrow('Could not ack message.');

        $this->logger->debug(__METHOD__ . '(): Acknowledgement attempt', [
            'delivery_tag' => $deliveryTag,
            'flags' => $flags,
            'queue' => $this->queueName,
        ]);

        try {
            $amqplibChannel->basic_ack($deliveryTag, (bool) ($flags & AMQP_MULTIPLE));
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, AMQPQueueException::class, __METHOD__);
        }

        $this->logger->debug(__METHOD__ . '(): Message acknowledged');

        return true;
    }

    /**
     * Binds the given queue to a routing key on an exchange.
     *
     * @param string $exchangeName Name of the exchange to bind to.
     * @param string|null $routingKey Pattern or routing key to bind with.
     * @param array<string, mixed> $arguments Additional binding arguments.

     * @throws AMQPChannelException If the channel is not open.
     * @throws AMQPConnectionException If the connection to the broker was lost.
     */
    public function bind(string $exchangeName, ?string $routingKey = null, array $arguments = []): bool
    {
        $routingKey ??= '';
        $amqplibChannel = $this->checkChannelOrThrow('Could not bind queue.');

        try {
            $amqplibChannel->queue_bind(
                $this->queueName,
                $exchangeName,
                $routingKey,
                false,
                $arguments
            );
        } catch (AMQPExceptionInterface $exception) {
            // TODO: Handle errors identically to php-amqp.
            throw new AMQPQueueException(__METHOD__ . ' failed: ' . $exception->getMessage());
        }

        return true;
    }

    /**
     * Cancels a queue that is already bound to an exchange and routing key.
     *
     * @param string $consumerTag  The consumer tag to cancel. If no tag provided,
     *                             or it is empty string, the latest consumer
     *                             tag on this queue will be used and after
     *                             successful request it will set to null.
     *                             If it is also empty, no `basic.cancel`
     *                             request will be sent. When consumer_tag is given,
     *                             and it is the same as the latest consumer_tag on queue,
     *                             it will be interpreted as the latest consumer_tag usage.
     *
     * @return bool;
     * @throws AMQPConnectionException If the connection to the broker was lost.
     * @throws AMQPChannelException If the channel is not open.
     */
    public function cancel(string $consumerTag = ''): bool
    {
        $amqplibChannel = $this->checkChannelOrThrow('Could not cancel queue.');

        if ($consumerTag === '') {
            $consumerTag = $this->lastConsumerTag ?? '';
        }

        try {
            $amqplibChannel->basic_cancel($consumerTag);
        } catch (AMQPExceptionInterface $exception) {
            // TODO: Handle errors identically to php-amqp.
            throw new AMQPQueueException(__METHOD__ . ' failed: ' . $exception->getMessage());
        }

        $this->channelBridge->unsubscribeConsumer($consumerTag);

        return true;
    }

    /**
     * Ensures the channel is usable or bails out if not.
     *
     * @throws AMQPChannelException
     * @throws AMQPConnectionException
     */
    private function checkChannelOrThrow(string $error): AmqplibChannel
    {
        if ($this->amqplibChannel === null) {
            throw new AMQPChannelException($error . ' Stale reference to the channel object.');
        }

        if (!$this->amqplibChannel->is_open()) {
            throw new AMQPChannelException($error . ' No channel available.');
        }

        if ($this->amqplibChannel->getConnection() === null) {
            throw new AMQPChannelException($error . ' Stale reference to the connection object.');
        }

        if (!$this->amqplibChannel->getConnection()->isConnected()) {
            throw new AMQPConnectionException($error . 'No connection available.');
        }

        return $this->amqplibChannel;
    }

    /**
     * Consumes messages from a queue.
     *
     * Blocking function that will retrieve the next message from the queue as
     * it becomes available and will pass it off to the callback.
     *
     * @param callable|null $callback   A callback function to which the
     *                                  consumed message will be passed. The
     *                                  function must accept at a minimum
     *                                  one parameter, an AMQPEnvelope object,
     *                                  and an optional second parameter
     *                                  the AMQPQueue object from which callback
     *                                  was invoked. The AMQPQueue::consume() will
     *                                  not return the processing thread back to
     *                                  the PHP script until the callback
     *                                  function returns FALSE.
     *                                  If the callback is omitted or null is passed,
     *                                  then the messages delivered to this client will
     *                                  be made available to the first real callback
     *                                  registered. That allows you to have a single
     *                                  callback consuming from multiple queues.
     * @param integer $flags            A bitmask of any of the flags: AMQP_AUTOACK,
     *                                  AMQP_JUST_CONSUME. Note: when AMQP_JUST_CONSUME
     *                                  flag is used, all other flags are ignored and
     *                                  $consumerTag parameter makes no sense.
     *                                  AMQP_JUST_CONSUME flag prevents sending the
     *                                  `basic.consume` request and just runs $callback
     *                                  if provided. Calling the method with empty $callback
     *                                  and AMQP_JUST_CONSUME makes no sense.
     * @param string|null $consumerTag  A string describing this consumer. Used
     *                                  for canceling subscriptions with ->cancel().
     *
     * @throws AMQPChannelException     If the channel is not open.
     * @throws AMQPConnectionException  If the connection to the broker was lost.
     * @throws AMQPEnvelopeException    When no queue found for envelope.
     * @throws AMQPQueueException       If timeout occurs or queue does not exist.
     */
    public function consume(
        ?callable $callback = null,
        int $flags = AMQP_NOPARAM,
        ?string $consumerTag = null
    ): void {
        $amqplibChannel = $this->checkChannelOrThrow('Could not get channel.');

        $justConsume = $flags & AMQP_JUST_CONSUME;
        $isSubscription = $callback !== null && !$justConsume;

        $this->logger->debug(
            __METHOD__ . '(): Consumer ' . ($isSubscription ? 'subscription' : 'start') . ' attempt',
            [
                'consumer_tag' => $consumerTag,
                'flags' => $flags,
                'queue' => $this->queueName,
                'subscribed_consumers' => array_map(
                    static fn (AMQPQueue $amqpQueue) => $amqpQueue->getName(),
                    $this->channelBridge->getSubscribedConsumers()
                )
            ]
        );

        // AMQP_JUST_CONSUME means "don't subscribe a consumer, just start consuming".
        if (!$justConsume) {
            try {
                $consumerTag = $amqplibChannel->basic_consume(
                    $this->queueName,
                    $consumerTag,
                    (bool)($flags & AMQP_NOLOCAL),
                    (bool)($flags & AMQP_AUTOACK), // A.K.A "no_ack".
                    $this->exclusive,
                    false, // FIXME.
                    function (AmqplibMessage $message) {
                        $amqpEnvelope = $this->envelopeTransformer->transformMessage($message);

                        if (!$this->channelBridge->isConsumerSubscribed($message->getConsumerTag())) {
                            // We received an envelope for a consumer tag that isn't subscribed.
                            $exception = new AMQPEnvelopeException('Orphaned envelope');

                            // The reference API defines this as a public property and is assigned at the call site.
                            $exception->envelope = $amqpEnvelope;

                            throw $exception;
                        }

                        $this->channelBridge->consumeEnvelope($amqpEnvelope);
                    },
                    null,
                    [] // FIXME.
                );
            } catch (AMQPExceptionInterface $exception) {
                /** @var AMQPExceptionInterface&Exception $exception */
                $this->exceptionHandler->handleException($exception, AMQPQueueException::class, __METHOD__);
            }

            // Record the most recent consumer tag as it may be fetched by ->getConsumerTag().
            $this->lastConsumerTag = $consumerTag;

            $this->channelBridge->subscribeConsumer($consumerTag, $this);

            $this->logger->debug(__METHOD__ . '(): Consumer subscribed');
        } else {
            $this->logger->debug(__METHOD__ . '(): Just consuming - not subscribing');
        }

        if ($callback === null) {
            // Queue was only being subscribed to the list for consumption; do not start processing yet.

            $this->logger->debug(__METHOD__ . '(): Consumer not yet starting');

            return;
        }

        $this->channelBridge->setConsumptionCallback($callback);

        $consuming = true;

        while ($consuming) {
            try {
                /*
                 * Wait for a message to be delivered to the callback attached above via ->basic_consume(...).
                 *
                 * Amqplib's internal wait loop will allow async signals or tocks to still be fired,
                 * so that heartbeats can still be handled in between messages.
                 */
                $amqplibChannel->wait(
                    timeout: $this->channelBridge->getReadTimeout()
                );
            } catch (StopConsumptionException $exception) {
                // Consumer returned false, so we return control to the caller.
                $consuming = false;
            } catch (AMQPExceptionInterface $exception) {
                /** @var AMQPExceptionInterface&Exception $exception */
                $this->exceptionHandler->handleException(
                    $exception,
                    AMQPQueueException::class,
                    __METHOD__,
                    isConsumption: true
                );
            }
        }

        $this->logger->debug(__METHOD__ . '(): Consumer stopped');
    }

    /**
     * Declares a new or existing queue on the broker, returning the message count.
     *
     * @throws AMQPChannelException If the channel is not open.
     * @throws AMQPConnectionException If the connection to the broker was lost.
     * @throws AMQPQueueException On failure.
     */
    public function declareQueue(): int
    {
        $amqplibChannel = $this->checkChannelOrThrow('Could not declare queue.');

        try {
            $result = $amqplibChannel->queue_declare(
                $this->queueName,
                $this->passive,
                $this->durable,
                $this->exclusive,
                $this->autoDelete,
                $this->noWait,
                new AmqplibTable($this->arguments)
            );
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, AMQPQueueException::class, __METHOD__);
        }

        if (!is_array($result)) {
            throw new AMQPQueueException(__METHOD__ . '(): Amqplib result was not an array');
        }

        // If the queue name was auto-generated, we need to extract it.
        $this->queueName = $result[0];

        if (count($result) < 2) {
            throw new AMQPQueueException(__METHOD__ . '(): Amqplib result should contain message count at [1]');
        }

        return (int) $result[1];
    }

    /**
     * Deletes a queue from the broker, returning the number of deleted messages.
     *
     * This includes the entire contents of unread or unacknowledged messages.
     *
     * @param int $flags AMQP_IFUNUSED, indicating that the queue should not be
     *                   deleted until no clients are connected to it,
     *                   and/or AMQP_IFEMPTY.
     *
     * @throws AMQPChannelException If the channel is not open.
     * @throws AMQPConnectionException If the connection to the broker was lost.
     * @throws AMQPQueueException On failure.
     */
    public function delete(int $flags = AMQP_NOPARAM): int
    {
        $amqplibChannel = $this->checkChannelOrThrow('Could not delete queue.');

        $this->logger->debug(__METHOD__ . '(): Queue deletion attempt', [
            'flags' => $flags,
            'queue' => $this->queueName,
        ]);

        try {
            $result = $amqplibChannel->queue_delete(
                $this->queueName,
                (bool) ($flags & AMQP_IFUNUSED),
                (bool) ($flags & AMQP_IFEMPTY),
                $this->noWait
            );
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, AMQPQueueException::class, __METHOD__);
        }

        $this->logger->debug(__METHOD__ . '(): Queue deleted');

        return (int) $result;
    }

    /**
     * Retrieves the next message from the queue.
     *
     * Retrieve the next available message from the queue. If no messages are
     * present in the queue, this function will return FALSE immediately. This
     * is a non-blocking alternative to the AMQPQueue::consume() method.
     * Currently, the only supported flag for the flags parameter is
     * AMQP_AUTOACK. If this flag is passed in, then the message returned will
     * automatically be marked as acknowledged by the broker as soon as the
     * frames are sent to the client.
     *
     * @param integer $flags A bitmask of supported flags for the
     *                       method call. Currently, the only
     *                       supported flag is AMQP_AUTOACK. If this
     *                       value is not provided, it will use the
     *                       value of ini-setting amqp.auto_ack.
     *
     * @return AMQPEnvelope|false
     *
     * @throws AMQPChannelException If the channel is not open.
     * @throws AMQPConnectionException If the connection to the broker was lost.
     * @throws AMQPQueueException If queue does not exist.
     */
    public function get(int $flags = AMQP_NOPARAM): AMQPEnvelope|false
    {
        $amqplibChannel = $this->checkChannelOrThrow('Could not get messages from queue.');

        $this->logger->debug(__METHOD__ . '(): Message fetch attempt (get)', [
            'flags' => $flags,
            'queue' => $this->queueName,
        ]);

        try {
            $amqplibMessage = $amqplibChannel->basic_get(
                $this->queueName,
                (bool) ($flags & AMQP_AUTOACK) // A.K.A "no_ack".
            );
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, AMQPQueueException::class, __METHOD__);
        }

        if ($amqplibMessage === null) {
            $this->logger->debug(__METHOD__ . '(): No message available, none fetched');

            return false;
        }

        $this->logger->debug(__METHOD__ . '(): Message fetched', [
            'body' => $amqplibMessage->getBody(),
            'delivery_tag' => $amqplibMessage->getDeliveryTag(),
        ]);

        return $this->envelopeTransformer->transformMessage($amqplibMessage);
    }

    /**
     * Fetches the argument associated with the given key.
     *
     * @param string $key The key to look up.
     *
     * @return scalar The string or integer value associated
     *                with the given key, or false if the key
     *                is not set.
     */
    public function getArgument(string $key)
    {
        return $this->arguments[$key] ?? false;
    }

    /**
     * Fetches all set arguments as an array of key/value pairs.
     *
     * @return array<string, scalar>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * Fetches the AMQPChannel object in use.
     *
     * @return AMQPChannel
     */
    public function getChannel(): AMQPChannel
    {
        return $this->amqpChannel;
    }

    /**
     * Fetches the AMQPConnection object in use.
     *
     * @return AMQPConnection
     */
    public function getConnection(): AMQPConnection
    {
        return $this->amqpChannel->getConnection();
    }

    /**
     * Gets the latest consumer tag.
     * If no consumer is available or the latest one was canceled, null will be returned.
     */
    public function getConsumerTag(): ?string
    {
        return $this->lastConsumerTag;
    }

    /**
     * Fetches all the flags currently set on this queue.
     *
     * @return int An integer bitmask of all the flags currently set on this
     *             queue object.
     */
    public function getFlags(): int
    {
        $flags = 0;

        if ($this->autoDelete) {
            $flags |= AMQP_AUTODELETE;
        }

        if ($this->durable) {
            $flags |= AMQP_DURABLE;
        }

        if ($this->exclusive) {
            $flags |= AMQP_EXCLUSIVE;
        }

        if ($this->noWait) {
            $flags |= AMQP_NOWAIT;
        }

        if ($this->passive) {
            $flags |= AMQP_PASSIVE;
        }

        return $flags;
    }

    /**
     * Fetches the configured queue name.
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->queueName;
    }

    /**
     * Check whether a queue has specific argument.
     *
     * @param string $key The key to check.
     *
     * @return bool
     */
    public function hasArgument(string $key): bool
    {
        return array_key_exists($key, $this->arguments);
    }

    /**
     * Marks a message as explicitly negatively acknowledged (rejected).
     *
     * This method can only be called on messages that have not
     * yet been acknowledged, meaning that messages retrieved with by
     * AMQPQueue::consume() and AMQPQueue::get() and using the AMQP_AUTOACK
     * flag are not eligible. When called, the broker will immediately put the
     * message back onto the queue, instead of waiting until the connection is
     * closed. This method is only supported by the RabbitMQ broker. The
     * behavior of calling this method while connected to any other broker is
     * undefined.
     *
     * @param int $deliveryTag Delivery tag of last message to reject.
     * @param int $flags AMQP_REQUEUE to requeue the message(s),
     *                   AMQP_MULTIPLE to nack all previous
     *                   unacked messages as well.
     *
     * @return bool
     *
     * @throws AMQPConnectionException If the connection to the broker was lost.
     * @throws AMQPChannelException If the channel is not open.
     */
    public function nack(int $deliveryTag, int $flags = AMQP_NOPARAM): bool
    {
        $amqplibChannel = $this->checkChannelOrThrow('Could not nack message.');

        $this->logger->debug(__METHOD__ . '(): Negative acknowledgement attempt', [
            'delivery_tag' => $deliveryTag,
            'flags' => $flags,
            'queue' => $this->queueName,
        ]);

        try {
            $amqplibChannel->basic_nack(
                $deliveryTag,
                (bool) ($flags & AMQP_MULTIPLE),
                (bool) ($flags & AMQP_REQUEUE)
            );
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, AMQPQueueException::class, __METHOD__);
        }

        $this->logger->debug(__METHOD__ . '(): Message negatively acknowledged');

        return true;
    }

    /**
     * Purges the contents of a queue.
     *
     * @return bool
     *
     * @throws AMQPChannelException If the channel is not open.
     * @throws AMQPConnectionException If the connection to the broker was lost.
     */
    public function purge(): bool
    {
        $amqplibChannel = $this->checkChannelOrThrow('Could not purge queue.');

        $this->logger->debug(__METHOD__ . '(): Queue messages purge attempt', [
            'queue' => $this->queueName,
        ]);

        try {
            $amqplibChannel->queue_purge($this->queueName, $this->noWait);
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, AMQPQueueException::class, __METHOD__);
        }

        $this->logger->debug(__METHOD__ . '(): Queue messages purged');

        return true;
    }

    /**
     * Marks one message as explicitly not acknowledged.
     *
     * Marks the message identified by delivery_tag as explicitly negatively
     * acknowledged. This method can only be called on messages that have not
     * yet been acknowledged, meaning that messages retrieved with by
     * AMQPQueue::consume() and AMQPQueue::get() and using the AMQP_AUTOACK
     * flag are not eligible.
     *
     * @param integer $deliveryTag Delivery tag of the message to reject.
     * @param integer $flags AMQP_REQUEUE to requeue the message(s).
     *
     * @return bool
     *
     * @throws AMQPConnectionException If the connection to the broker was lost.
     * @throws AMQPChannelException If the channel is not open.
     */
    public function reject(int $deliveryTag, int $flags = AMQP_NOPARAM): bool
    {
        $amqplibChannel = $this->checkChannelOrThrow('Could not reject message.');

        $this->logger->debug(__METHOD__ . '(): Message rejection attempt', [
            'delivery_tag' => $deliveryTag,
            'flags' => $flags,
            'queue' => $this->queueName,
        ]);

        try {
            // Note from reference implementation: `basic.reject` is asynchronous,
            // and thus will not indicate failure if something goes wrong on the broker.
            $amqplibChannel->basic_reject(
                $deliveryTag,
                (bool) ($flags & AMQP_REQUEUE)
            );
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, AMQPQueueException::class, __METHOD__);
        }

        $this->logger->debug(__METHOD__ . '(): Message rejected');

        return true;
    }

    /**
     * Sets a queue argument.
     *
     * @throws AMQPQueueException When an invalid value is given.
     */
    public function setArgument(string $key, mixed $value): bool
    {
        if ($value !== null && !is_int($value) && !is_float($value) && !is_string($value)) {
            throw new AMQPQueueException(
                'The value parameter must be of type NULL, int, double or string.'
            );
        }

        if ($value === null) {
            // When null is given, it is treated specially: it represents that the argument is to be removed.
            unset($this->arguments[$key]);
        } else {
            $this->arguments[$key] = $value;
        }

        return true;
    }

    /**
     * Sets all arguments on the given queue.
     *
     * All other argument settings will be wiped.
     *
     * @param array<string, scalar> $arguments
     */
    public function setArguments(array $arguments): bool
    {
        $this->arguments = $arguments;

        return true;
    }

    /**
     * Sets the flags on the queue.
     *
     * @param integer $flags A bitmask of flags:
     *                       AMQP_DURABLE, AMQP_PASSIVE,
     *                       AMQP_EXCLUSIVE, AMQP_AUTODELETE.
     */
    public function setFlags(int $flags): bool
    {
        $this->autoDelete = (bool)($flags & AMQP_AUTODELETE);
        $this->durable = (bool)($flags & AMQP_DURABLE);
        $this->exclusive = (bool)($flags & AMQP_EXCLUSIVE);
        $this->noWait = (bool)($flags & AMQP_NOWAIT);
        $this->passive = (bool)($flags & AMQP_PASSIVE);

        return true;
    }

    /**
     * Sets the queue name.
     */
    public function setName(string $queueName): bool
    {
        $this->queueName = $queueName;

        return true;
    }

    /**
     * Remove a routing key binding on an exchange from the given queue.
     *
     * @param string $exchangeName The name of the exchange on which the
     *                             queue is bound.
     * @param string $routingKey The binding routing key used by the
     *                           queue.
     * @param array<string, scalar> $arguments Additional binding arguments.
     *
     * @throws AMQPChannelException If the channel is not open.
     * @throws AMQPConnectionException If the connection to the broker was lost.
     */
    public function unbind(string $exchangeName, ?string $routingKey = null, array $arguments = []): bool
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }
}
