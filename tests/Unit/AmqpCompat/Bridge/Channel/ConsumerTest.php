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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\AmqpCompat\Bridge\Channel;

use AMQPEnvelope;
use AMQPQueue;
use Asmblah\PhpAmqpCompat\Bridge\Channel\Consumer;
use Asmblah\PhpAmqpCompat\Exception\StopConsumptionException;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use LogicException;
use Mockery\MockInterface;

/**
 * Class ConsumerTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ConsumerTest extends AbstractTestCase
{
    /**
     * @var (MockInterface&AMQPQueue)|null
     */
    private $amqpQueue;
    private Consumer|null $consumer;

    public function setUp(): void
    {
        $this->amqpQueue = mock(AMQPQueue::class);

        $this->consumer = new Consumer();
    }

    public function testConsumeEnvelopeThrowsWhenNoConsumptionCallbackIsSet(): void
    {
        $amqpEnvelope = mock(AMQPEnvelope::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'Asmblah\PhpAmqpCompat\Bridge\Channel\Consumer::consumeEnvelope :: No callback is registered'
        );

        $this->consumer->consumeEnvelope($amqpEnvelope, $this->amqpQueue);
    }

    public function testConsumeEnvelopeCallsCallbackWithCorrectEnvelopeAndQueue(): void
    {
        $amqpEnvelope = mock(AMQPEnvelope::class);
        /** @var AMQPEnvelope $passedEnvelope */
        $passedEnvelope = null;
        /** @var AMQPQueue $passedQueue */
        $passedQueue = null;
        $this->consumer->setConsumptionCallback(function ($envelope, $queue) use (&$passedEnvelope, &$passedQueue) {
            $passedEnvelope = $envelope;
            $passedQueue = $queue;
        });

        $this->consumer->consumeEnvelope($amqpEnvelope, $this->amqpQueue);

        static::assertSame($amqpEnvelope, $passedEnvelope);
        static::assertSame($this->amqpQueue, $passedQueue);
    }

    public function testConsumeEnvelopeThrowsStopExceptionWhenCallbackReturnsFalse(): void
    {
        $amqpEnvelope = mock(AMQPEnvelope::class);
        $this->consumer->setConsumptionCallback(function (): bool {
            return false;
        });

        $this->expectException(StopConsumptionException::class);

        $this->consumer->consumeEnvelope($amqpEnvelope, $this->amqpQueue);
    }

    public function testGetConsumptionCallbackFetchesTheCallback(): void
    {
        $consumptionCallback = function () {};
        $this->consumer->setConsumptionCallback($consumptionCallback);

        static::assertSame($consumptionCallback, $this->consumer->getConsumptionCallback());
    }
}
