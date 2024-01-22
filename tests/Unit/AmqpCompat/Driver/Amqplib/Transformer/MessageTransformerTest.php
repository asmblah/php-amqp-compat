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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\AmqpCompat\Driver\Amqplib\Transformer;

use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transformer\MessageTransformer;
use Asmblah\PhpAmqpCompat\Driver\Common\Processor\ValueProcessorInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Mockery\MockInterface;
use PhpAmqpLib\Wire\AMQPTable as AmqplibTable;

/**
 * Class MessageTransformerTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class MessageTransformerTest extends AbstractTestCase
{
    private MessageTransformer $transformer;
    private MockInterface&ValueProcessorInterface $valueProcessor;

    public function setUp(): void
    {
        $this->valueProcessor = mock(ValueProcessorInterface::class);

        $this->valueProcessor->allows('processValueForDriver')
            ->andReturnUsing(function (mixed $value) {
                return $value;
            })
            ->byDefault();

        $this->transformer = new MessageTransformer($this->valueProcessor);
    }

    public function testTransformEnvelopeReturnsAmqplibMessageIncludingBody(): void
    {
        $amqplibMessage = $this->transformer->transformEnvelope('my message body', []);

        static::assertSame('my message body', $amqplibMessage->getBody());
    }

    public function testTransformEnvelopeRemovesEmptyAttributes(): void
    {
        $amqplibMessage = $this->transformer->transformEnvelope('my message body', [
            'user_id' => '',
        ]);

        static::assertArrayNotHasKey('user_id', $amqplibMessage->get_properties());
    }

    public function testTransformEnvelopeProcessesAttributesViaValueProcessor(): void
    {
        $this->valueProcessor->allows()
            ->processValueForDriver([
                'headers' => ['x-my-header' => 'hello']
            ])
            ->andReturn([
                'headers' => ['x-my-header' => 'world']
            ]);

        $amqplibMessage = $this->transformer->transformEnvelope('my message body', [
            'headers' => [
                'x-my-header' => 'hello',
            ],
        ]);

        $headersTable = $amqplibMessage->get('application_headers');
        static::assertInstanceOf(AmqplibTable::class, $headersTable);
        static::assertSame('world', $headersTable['x-my-header']);
    }

    public function testTransformEnvelopeHandlesHeaders(): void
    {
        $amqplibMessage = $this->transformer->transformEnvelope('my message body', [
            'headers' => [
                'x-my-first-header' => 'my first value',
                'x-my-second-header' => 'my second value',
            ],
        ]);

        $headersTable = $amqplibMessage->get('application_headers');
        static::assertInstanceOf(AmqplibTable::class, $headersTable);
        static::assertSame('my first value', $headersTable['x-my-first-header']);
        static::assertSame('my second value', $headersTable['x-my-second-header']);
    }

    public function testTransformEnvelopeCastsAppIdToStringWhenSpecified(): void
    {
        /*
         * @phpstan-ignore-next-line Ignore the deliberate type error.
         */
        $amqplibMessage = $this->transformer->transformEnvelope('my message body', [
            'app_id' => 1234,
        ]);

        static::assertSame('1234', $amqplibMessage->get('app_id'));
    }

    public function testTransformEnvelopeCastsContentEncodingToStringWhenSpecified(): void
    {
        /*
         * @phpstan-ignore-next-line Ignore the deliberate type error.
         */
        $amqplibMessage = $this->transformer->transformEnvelope('my message body', [
            'content_encoding' => 1234,
        ]);

        static::assertSame('1234', $amqplibMessage->get('content_encoding'));
    }

    public function testTransformEnvelopeCastsCorrelationIdToStringWhenSpecified(): void
    {
        $amqplibMessage = $this->transformer->transformEnvelope('my message body', [
            'correlation_id' => 321,
        ]);

        static::assertSame('321', $amqplibMessage->get('correlation_id'));
    }

    public function testTransformEnvelopeCastsDeliveryModeToIntegerWhenSpecified(): void
    {
        $amqplibMessage = $this->transformer->transformEnvelope('my message body', [
            'delivery_mode' => '345',
        ]);

        static::assertSame(345, $amqplibMessage->get('delivery_mode'));
    }

    public function testTransformEnvelopeCastsMessageIdToStringWhenSpecified(): void
    {
        /*
         * @phpstan-ignore-next-line Ignore the deliberate type error.
         */
        $amqplibMessage = $this->transformer->transformEnvelope('my message body', [
            'message_id' => 543,
        ]);

        static::assertSame('543', $amqplibMessage->get('message_id'));
    }

    public function testTransformEnvelopeCastsPriorityToIntegerWhenSpecified(): void
    {
        $amqplibMessage = $this->transformer->transformEnvelope('my message body', [
            'priority' => '21',
        ]);

        static::assertSame(21, $amqplibMessage->get('priority'));
    }

    public function testTransformEnvelopeCastsReplyToToStringWhenSpecified(): void
    {
        /*
         * @phpstan-ignore-next-line Ignore the deliberate type error.
         */
        $amqplibMessage = $this->transformer->transformEnvelope('my message body', [
            'reply_to' => 543,
        ]);

        static::assertSame('543', $amqplibMessage->get('reply_to'));
    }

    public function testTransformEnvelopeCastsTimestampToIntegerWhenSpecified(): void
    {
        $amqplibMessage = $this->transformer->transformEnvelope('my message body', [
            'timestamp' => '123456789',
        ]);

        static::assertSame(123456789, $amqplibMessage->get('timestamp'));
    }

    public function testTransformEnvelopeCastsTypeToStringWhenSpecified(): void
    {
        /*
         * @phpstan-ignore-next-line Ignore the deliberate type error.
         */
        $amqplibMessage = $this->transformer->transformEnvelope('my message body', [
            'type' => 654,
        ]);

        static::assertSame('654', $amqplibMessage->get('type'));
    }

    public function testTransformEnvelopeSetsContentTypeAsTextPlainIfUnspecified(): void
    {
        $amqplibMessage = $this->transformer->transformEnvelope('my message body', []);

        static::assertSame('text/plain', $amqplibMessage->get('content_type'));
    }

    public function testTransformEnvelopeCastsContentTypeToStringWhenSpecified(): void
    {
        /*
         * @phpstan-ignore-next-line Ignore the deliberate type error.
         */
        $amqplibMessage = $this->transformer->transformEnvelope('my message body', [
            'content_type' => 123,
        ]);

        static::assertSame('123', $amqplibMessage->get('content_type'));
    }

    public function testTransformEnvelopeDoesNotOverrideContentTypeHeaderIfSpecified(): void
    {
        $amqplibMessage = $this->transformer->transformEnvelope('my message body', [
            'content_type' => 'text/x-my-type'
        ]);

        static::assertSame('text/x-my-type', $amqplibMessage->get('content_type'));
    }

    public function testTransformEnvelopeAlsoSetsContentEncodingOnPublicProperty(): void
    {
        $amqplibMessage = $this->transformer->transformEnvelope('my message body', [
            'content_encoding' => 'x-my-encoding'
        ]);

        static::assertSame('x-my-encoding', $amqplibMessage->getContentEncoding());
        static::assertSame('x-my-encoding', $amqplibMessage->get('content_encoding'));
    }
}
