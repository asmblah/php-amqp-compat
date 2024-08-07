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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\Amqp;

use AMQPEnvelope;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;

/**
 * Class AMQPEnvelopeTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AMQPEnvelopeTest extends AbstractTestCase
{
    public function testSupportsAllPropertiesBeingProvidedCorrectly(): void
    {
        $envelope = new AMQPEnvelope(
            // Values AMQPEnvelope adds on top of AMQPBasicProperties.
            'my body',
            'my-consumer-tag',
            12333,
            'my-exchange',
            false,
            'my-routing-key',

            // AMQPBasicProperties values.
            'my-content-type',
            'my-encoding',
            ['x-header-1' => 'first', 'x-header-2' => 'second'],
            AMQP_DELIVERY_MODE_PERSISTENT,
            21,
            'my-correlation-id',
            'my-reply-to',
            'my-expiration',
            'my-message-id-123',
            4567,
            'my-type',
            'my-user-id',
            'my-app-id',
            'my-cluster-id',
        );

        // Values AMQPEnvelope adds on top of AMQPBasicProperties.
        static::assertSame('my body', $envelope->getBody());
        static::assertSame('my-consumer-tag', $envelope->getConsumerTag());
        static::assertSame(12333, $envelope->getDeliveryTag());
        static::assertSame('my-exchange', $envelope->getExchangeName());
        static::assertSame(false, $envelope->isRedelivery());
        static::assertSame('my-routing-key', $envelope->getRoutingKey());

        // AMQPBasicProperties values.
        static::assertSame('my-content-type', $envelope->getContentType());
        static::assertSame('my-encoding', $envelope->getContentEncoding());
        static::assertEquals(['x-header-1' => 'first', 'x-header-2' => 'second'], $envelope->getHeaders());
        static::assertSame(AMQP_DELIVERY_MODE_PERSISTENT, $envelope->getDeliveryMode());
        static::assertSame(21, $envelope->getPriority());
        static::assertSame('my-correlation-id', $envelope->getCorrelationId());
        static::assertSame('my-reply-to', $envelope->getReplyTo());
        static::assertSame('my-expiration', $envelope->getExpiration());
        static::assertSame('my-message-id-123', $envelope->getMessageId());
        static::assertSame(4567, $envelope->getTimestamp());
        static::assertSame('my-type', $envelope->getType());
        static::assertSame('my-user-id', $envelope->getUserId());
        static::assertSame('my-app-id', $envelope->getAppId());
        static::assertSame('my-cluster-id', $envelope->getClusterId());
    }

    public function testSupportsAllPropertyDefaultsCorrectly(): void
    {
        $envelope = new AMQPEnvelope();

        // Values AMQPEnvelope adds on top of AMQPBasicProperties.
        static::assertSame('', $envelope->getBody()); // Unlike other string getters which return null.
        static::assertNull($envelope->getConsumerTag());
        static::assertNull($envelope->getDeliveryTag());
        static::assertNull($envelope->getExchangeName());
        static::assertNull($envelope->isRedelivery());
        static::assertNull($envelope->getRoutingKey());

        // AMQPBasicProperties values.
        static::assertSame('', $envelope->getContentType());
        static::assertSame('', $envelope->getContentEncoding());
        static::assertEquals([], $envelope->getHeaders());
        static::assertSame(AMQP_DELIVERY_MODE_TRANSIENT, $envelope->getDeliveryMode());
        static::assertSame(0, $envelope->getPriority());
        static::assertSame('', $envelope->getCorrelationId());
        static::assertSame('', $envelope->getReplyTo());
        static::assertSame('', $envelope->getExpiration());
        static::assertSame('', $envelope->getMessageId());
        static::assertSame(0, $envelope->getTimestamp());
        static::assertSame('', $envelope->getType());
        static::assertSame('', $envelope->getUserId());
        static::assertSame('', $envelope->getAppId());
        static::assertSame('', $envelope->getClusterId());
    }

    public function testGetHeaderFetchesTheSetHeader(): void
    {
        $envelope = new AMQPEnvelope(headers: [
            'x-my-array' => ['first', 'second'],
            'x-my-true-boolean' => true,
            'x-my-false-boolean' => false,
            'x-my-float' => 123.456,
            'x-my-integer' => 234,
            'x-my-null' => null,
            'x-my-string' => 'my string',
        ]);

        static::assertEquals(['first', 'second'], $envelope->getHeader('x-my-array'));
        static::assertTrue($envelope->getHeader('x-my-true-boolean'));
        static::assertFalse($envelope->getHeader('x-my-false-boolean'));
        static::assertSame(123.456, $envelope->getHeader('x-my-float'));
        static::assertSame(234, $envelope->getHeader('x-my-integer'));
        static::assertNull($envelope->getHeader('x-my-null'));
        static::assertSame('my string', $envelope->getHeader('x-my-string'));
    }
}
