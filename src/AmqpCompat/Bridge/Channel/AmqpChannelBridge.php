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

use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use PhpAmqpLib\Channel\AMQPChannel as AmqplibChannel;
use PhpAmqpLib\Message\AMQPMessage as AmqplibMessage;

class AmqpChannelBridge implements AmqpChannelBridgeInterface
{
    private array $consumerTags = [];

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
        $this->consumer->consumeMessage($message);
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
    public function isConsumerSubscribed(string $consumerTag): bool
    {
        return array_key_exists($consumerTag, $this->consumerTags);
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
    public function subscribeConsumer(string $consumerTag): void
    {
        $this->consumerTags[$consumerTag] = true;
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
        unset($this->consumerTags[$consumerTag]);
    }
}
