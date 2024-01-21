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
use Asmblah\PhpAmqpCompat\Driver\Common\Processor\ValueProcessorInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use LogicException;
use Mockery\MockInterface;
use PhpAmqpLib\Message\AMQPMessage as AmqplibMessage;
use PhpAmqpLib\Wire\AMQPTable as AmqplibTable;

/**
 * Class EnvelopeTransformerTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class EnvelopeTransformerTest extends AbstractTestCase
{
    private MockInterface&AmqplibMessage $amqplibMessage;
    private EnvelopeTransformer $envelopeTransformer;
    private MockInterface&ValueProcessorInterface $valueProcessor;

    public function setUp(): void
    {
        $this->amqplibMessage = mock(AmqplibMessage::class, [
            'getBody' => 'my message body',
            'getConsumerTag' => 'my-consumer-tag',
            'getContentEncoding' => 'application/x-my-encoding',
            'getDeliveryTag' => 4321,
            'getExchange' => 'my-exchange',
            'getRoutingKey' => 'my-routing-key',
            'get_properties' => [
                'application_headers' => new AmqplibTable([
                    'x-my-first-header' => 'my first value',
                    'x-my-second-header' => 'my second value',
                    'x-my-transformed-header' => 'my original value',
                ]),
                'content_type' => 'text/x-custom',
            ],
            'isRedelivered' => false,
        ]);
        $this->valueProcessor = mock(ValueProcessorInterface::class);

        $this->valueProcessor->allows('processValueFromDriver')
            ->andReturnArg(0)
            ->byDefault();
        $this->valueProcessor->allows()
            ->processValueFromDriver([
                'x-my-first-header' => 'my first value',
                'x-my-second-header' => 'my second value',
                'x-my-transformed-header' => 'my original value',
            ])
            ->andReturn([
                'x-my-first-header' => 'my first value',
                'x-my-second-header' => 'my second value',
                'x-my-transformed-header' => 'my transformed value',
            ])
            ->byDefault();

        $this->envelopeTransformer = new EnvelopeTransformer($this->valueProcessor);
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
        static::assertEquals(
            [
                'x-my-first-header' => 'my first value',
                'x-my-second-header' => 'my second value',
                'x-my-transformed-header' => 'my transformed value',
            ],
            $amqpEnvelope->getHeaders()
        );
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

    public function testTransformMessageReturnsEnvelopeWithEmptyHeadersWhenNoneReturnedFromDriver(): void
    {
        $this->amqplibMessage->allows('get_properties')
            ->andReturn(['content_type' => 'text/x-custom']);

        $amqpEnvelope = $this->envelopeTransformer->transformMessage($this->amqplibMessage);

        static::assertInstanceOf(AMQPEnvelope::class, $amqpEnvelope);
        static::assertEmpty($amqpEnvelope->getHeaders());
    }

    public function testTransformMessageRaisesExceptionWhenInvalidHeadersTableIsGiven(): void
    {
        $this->amqplibMessage->allows('get_properties')
            ->andReturn([
                'application_headers' => 'I am not a valid headers table',
            ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            EnvelopeTransformer::class . '::transformMessage() :: application_headers is not an AMQPTable'
        );

        $this->envelopeTransformer->transformMessage($this->amqplibMessage);
    }
}
