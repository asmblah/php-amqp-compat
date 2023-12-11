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
use Asmblah\PhpAmqpCompat\Bridge\Channel\EnvelopeTransformer;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Mockery\MockInterface;
use PhpAmqpLib\Message\AMQPMessage as AmqplibMessage;

/**
 * Class EnvelopeTransformerTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class EnvelopeTransformerTest extends AbstractTestCase
{
    private MockInterface&AmqplibMessage $amqplibMessage;
    private EnvelopeTransformer $envelopeTransformer;

    public function setUp(): void
    {
        $this->amqplibMessage = mock(AmqplibMessage::class, [
            'getBody' => 'my message body',
            'getConsumerTag' => 'my-consumer-tag',
            'getContentEncoding' => 'application/x-my-encoding',
            'getDeliveryTag' => 4321,
            'getExchange' => 'my-exchange',
            'getRoutingKey' => 'my-routing-key',
            'get_properties' => ['content_type' => 'text/x-custom'],
            'isRedelivered' => false,
        ]);

        $this->envelopeTransformer = new EnvelopeTransformer();
    }

    public function testTransformMessageReturnsCorrectlyConstructedEnvelopeWhenPopulated(): void
    {
        $amqpEnvelope = $this->envelopeTransformer->transformMessage($this->amqplibMessage);

        static::assertInstanceOf(AMQPEnvelope::class, $amqpEnvelope);
        static::assertSame('my message body', $amqpEnvelope->getBody());
        static::assertSame('my-consumer-tag', $amqpEnvelope->getConsumerTag());
        static::assertSame('application/x-my-encoding', $amqpEnvelope->getContentEncoding());
        static::assertSame(4321, $amqpEnvelope->getDeliveryTag());
        static::assertSame('my-exchange', $amqpEnvelope->getExchangeName());
        static::assertSame('my-routing-key', $amqpEnvelope->getRoutingKey());
        static::assertFalse($amqpEnvelope->isRedelivery());
    }

    public function testTransformMessageReturnsCorrectlyConstructedEnvelopeWhenEmpty(): void
    {
        $this->amqplibMessage = mock(AmqplibMessage::class, [
            'getBody' => '',
            'getConsumerTag' => null,
            'getContentEncoding' => 'application/x-my-encoding',
            'getDeliveryTag' => 4321,
            'getExchange' => null,
            'getRoutingKey' => null,
            'get_properties' => ['content_type' => 'text/x-custom'],
            'isRedelivered' => null,
        ]);

        $amqpEnvelope = $this->envelopeTransformer->transformMessage($this->amqplibMessage);

        static::assertInstanceOf(AMQPEnvelope::class, $amqpEnvelope);
        static::assertFalse($amqpEnvelope->getBody());
        static::assertSame('', $amqpEnvelope->getConsumerTag());
        static::assertSame('application/x-my-encoding', $amqpEnvelope->getContentEncoding());
        static::assertSame(4321, $amqpEnvelope->getDeliveryTag());
        static::assertNull($amqpEnvelope->getExchangeName());
        static::assertNull($amqpEnvelope->getRoutingKey());
        static::assertNull($amqpEnvelope->isRedelivery());
    }
}
