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

use Asmblah\PhpAmqpCompat\Bridge\AmqpBridgeResourceInterface;
use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use PhpAmqpLib\Channel\AMQPChannel as AmqplibChannel;
use PhpAmqpLib\Message\AMQPMessage as AmqplibMessage;

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
     * Consumes the given message, returning false if further consumption should be stopped.
     */
    public function consumeMessage(AmqplibMessage $message): void;

    /**
     * Fetches the internal php-amqplib channel.
     */
    public function getAmqplibChannel(): AmqplibChannel;

    /**
     * Fetches the AMQP connection bridge.
     */
    public function getConnectionBridge(): AmqpConnectionBridgeInterface;

    /**
     * Fetches the callback to use for consuming AMQP messages, if any.
     */
    public function getConsumptionCallback(): callable;

    /**
     * Determines whether a consumer with the given tag is subscribed.
     */
    public function isConsumerSubscribed(string $consumerTag): bool;

    /**
     * Sets the callback to use for consuming AMQP messages.
     */
    public function setConsumptionCallback(callable $callback): void;

    /**
     * Subscribes the given consumer.
     */
    public function subscribeConsumer(string $consumerTag): void;

    /**
     * Unregisters this channel from its connection.
     */
    public function unregisterChannel(): void;

    /**
     * Unsubscribes the given consumer.
     */
    public function unsubscribeConsumer(string $consumerTag): void;
}
