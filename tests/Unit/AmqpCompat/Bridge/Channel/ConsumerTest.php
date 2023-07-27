<?php

/*
 * PHP AMQP-Compat - php-amqp/ext-amqp compatibility.
 * Copyright (c) Dan Phillimore (asmblah)
 * https://github.com/asmblah/php-amqp-compat/
 *
 * Released under the MIT license.
 * https://github.com/asmblah/php-amqp-compat/raw/master/MIT-LICENSE.txt
 */

declare(strict_types=1);

namespace Asmblah\PhpAmqpCompat\Tests\Unit\AmqpCompat\Bridge\Channel;

use AMQPEnvelope;
use Asmblah\PhpAmqpCompat\Bridge\Channel\Consumer;
use Asmblah\PhpAmqpCompat\Exception\StopConsumptionException;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use LogicException;
use PhpAmqpLib\Message\AMQPMessage as AmqplibMessage;

/**
 * Class ConsumerTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ConsumerTest extends AbstractTestCase
{
    private Consumer|null $consumer;

    public function setUp(): void
    {
        $this->consumer = new Consumer();
    }

    public function testConsumeMessageThrowsWhenNoConsumptionCallbackIsSet(): void
    {
        $message = mock(AmqplibMessage::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'Asmblah\PhpAmqpCompat\Bridge\Channel\Consumer::consumeMessage :: No callback is registered'
        );

        $this->consumer->consumeMessage($message);
    }

    public function testConsumeMessageCallsCallbackWithCorrectEnvelope(): void
    {
        $message = mock(AmqplibMessage::class, [
            'getBody' => 'my message body',
            'getConsumerTag' => 'my-consumer-tag',
            'getContentEncoding' => 'application/x-my-encoding',
            'getDeliveryTag' => 4321,
            'getExchange' => 'my-exchange',
            'getRoutingKey' => 'my-routing-key',
            'get_properties' => [],
            'isRedelivered' => false,
        ]);
        /** @var AMQPEnvelope $passedEnvelope */
        $passedEnvelope = null;
        $this->consumer->setConsumptionCallback(function ($envelope) use (&$passedEnvelope) {
            $passedEnvelope = $envelope;
        });

        $this->consumer->consumeMessage($message);

        static::assertInstanceOf(AMQPEnvelope::class, $passedEnvelope);
        static::assertSame('my message body', $passedEnvelope->getBody());
        static::assertSame('my-consumer-tag', $passedEnvelope->getConsumerTag());
        static::assertSame('application/x-my-encoding', $passedEnvelope->getContentEncoding());
        static::assertSame(4321, $passedEnvelope->getDeliveryTag());
        static::assertSame('my-exchange', $passedEnvelope->getExchangeName());
        static::assertSame('my-routing-key', $passedEnvelope->getRoutingKey());
        static::assertFalse($passedEnvelope->isRedelivery());
    }

    public function testConsumeMessageThrowsStopExceptionWhenCallbackReturnsFalse(): void
    {
        $message = mock(AmqplibMessage::class, [
            'getBody' => 'my message body',
            'getConsumerTag' => 'my-consumer-tag',
            'getContentEncoding' => 'application/x-my-encoding',
            'getDeliveryTag' => 4321,
            'getExchange' => 'my-exchange',
            'getRoutingKey' => 'my-routing-key',
            'get_properties' => [],
            'isRedelivered' => false,
        ]);
        $this->consumer->setConsumptionCallback(function (): bool {
            return false;
        });

        $this->expectException(StopConsumptionException::class);

        $this->consumer->consumeMessage($message);
    }

    public function testGetConsumptionCallbackFetchesTheCallback(): void
    {
        $consumptionCallback = function () {};
        $this->consumer->setConsumptionCallback($consumptionCallback);

        static::assertSame($consumptionCallback, $this->consumer->getConsumptionCallback());
    }
}
