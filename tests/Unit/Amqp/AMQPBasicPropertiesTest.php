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

use AMQPBasicProperties;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;

/**
 * Class AMQPBasicPropertiesTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AMQPBasicPropertiesTest extends AbstractTestCase
{
    public function testSupportsAllPropertiesBeingProvidedCorrectly(): void
    {
        $properties = new AMQPBasicProperties(
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

        static::assertSame('my-content-type', $properties->getContentType());
        static::assertSame('my-encoding', $properties->getContentEncoding());
        static::assertEquals(['x-header-1' => 'first', 'x-header-2' => 'second'], $properties->getHeaders());
        static::assertSame(AMQP_DELIVERY_MODE_PERSISTENT, $properties->getDeliveryMode());
        static::assertSame(21, $properties->getPriority());
        static::assertSame('my-correlation-id', $properties->getCorrelationId());
        static::assertSame('my-reply-to', $properties->getReplyTo());
        static::assertSame('my-expiration', $properties->getExpiration());
        static::assertSame('my-message-id-123', $properties->getMessageId());
        static::assertSame(4567, $properties->getTimestamp());
        static::assertSame('my-type', $properties->getType());
        static::assertSame('my-user-id', $properties->getUserId());
        static::assertSame('my-app-id', $properties->getAppId());
        static::assertSame('my-cluster-id', $properties->getClusterId());
    }

    public function testSupportsAllPropertyDefaultsCorrectly(): void
    {
        $properties = new AMQPBasicProperties();

        static::assertSame('', $properties->getContentType());
        static::assertSame('', $properties->getContentEncoding());
        static::assertEquals([], $properties->getHeaders());
        static::assertSame(AMQP_DELIVERY_MODE_TRANSIENT, $properties->getDeliveryMode());
        static::assertSame(0, $properties->getPriority());
        static::assertSame('', $properties->getCorrelationId());
        static::assertSame('', $properties->getReplyTo());
        static::assertSame('', $properties->getExpiration());
        static::assertSame('', $properties->getMessageId());
        static::assertSame(0, $properties->getTimestamp());
        static::assertSame('', $properties->getType());
        static::assertSame('', $properties->getUserId());
        static::assertSame('', $properties->getAppId());
        static::assertSame('', $properties->getClusterId());
    }
}
