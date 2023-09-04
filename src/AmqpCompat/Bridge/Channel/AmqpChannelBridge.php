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

use AMQPQueue;
use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Error\ErrorReporterInterface;
use Asmblah\PhpAmqpCompat\Logger\LoggerInterface;
use LogicException;
use PhpAmqpLib\Channel\AMQPChannel as AmqplibChannel;
use PhpAmqpLib\Message\AMQPMessage as AmqplibMessage;

/**
 * Class AmqpChannelBridge.
 *
 * Defines the internal representation of an AMQP channel for this library.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqpChannelBridge implements AmqpChannelBridgeInterface
{
    /**
     * @var array<string, AMQPQueue>
     */
    private array $consumerTagToQueueMap = [];

    public function __construct(
        private readonly AmqpConnectionBridgeInterface $connectionBridge,
        private readonly AmqplibChannel $amqplibChannel,
        private readonly ConsumerInterface $consumer
    ) {
    }

    /**
     * @inheritDoc
     */
    public function consumeMessage(AmqplibMessage $message): void
    {
        $consumerTag = $message->getConsumerTag();
        $amqpQueue = $this->consumerTagToQueueMap[$consumerTag] ?? null;

        if ($amqpQueue === null) {
            throw new LogicException(sprintf(
                '%s(): No consumer registered for consumer tag "%s"',
                __METHOD__,
                $consumerTag
            ));
        }

        $this->consumer->consumeMessage($message, $amqpQueue);
    }

    /**
     * @inheritDoc
     */
    public function getAmqplibChannel(): AmqplibChannel
    {
        return $this->amqplibChannel;
    }

    /**
     * @inheritDoc
     */
    public function getConnectionBridge(): AmqpConnectionBridgeInterface
    {
        return $this->connectionBridge;
    }

    /**
     * @inheritDoc
     */
    public function getConsumptionCallback(): callable
    {
        return $this->consumer->getConsumptionCallback();
    }

    /**
     * @inheritDoc
     */
    public function getErrorReporter(): ErrorReporterInterface
    {
        return $this->connectionBridge->getErrorReporter();
    }

    /**
     * @inheritDoc
     */
    public function getLogger(): LoggerInterface
    {
        return $this->connectionBridge->getLogger();
    }

    /**
     * @inheritDoc
     */
    public function isConsumerSubscribed(string $consumerTag): bool
    {
        return array_key_exists($consumerTag, $this->consumerTagToQueueMap);
    }

    /**
     * @inheritDoc
     */
    public function setConsumptionCallback(callable $callback): void
    {
        $this->consumer->setConsumptionCallback($callback);
    }

    /**
     * @inheritDoc
     */
    public function subscribeConsumer(string $consumerTag, AMQPQueue $amqpQueue): void
    {
        $this->consumerTagToQueueMap[$consumerTag] = $amqpQueue;
    }

    /**
     * @inheritDoc
     */
    public function unregisterChannel(): void
    {
        $this->connectionBridge->unregisterChannelBridge($this);
    }

    /**
     * @inheritDoc
     */
    public function unsubscribeConsumer(string $consumerTag): void
    {
        unset($this->consumerTagToQueueMap[$consumerTag]);
    }
}
