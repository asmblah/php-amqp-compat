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

    public function testTransformEnvelopeSetsContentTypeHeaderAsTextPlainIfUnspecified(): void
    {
        $amqplibMessage = $this->transformer->transformEnvelope('my message body', []);

        static::assertSame('text/plain', $amqplibMessage->get('content_type'));
    }

    public function testTransformEnvelopeDoesNotOverrideContentTypeHeaderIfSpecified(): void
    {
        $amqplibMessage = $this->transformer->transformEnvelope('my message body', [
            'content_type' => 'text/x-my-type'
        ]);

        static::assertSame('text/x-my-type', $amqplibMessage->get('content_type'));
    }
}
