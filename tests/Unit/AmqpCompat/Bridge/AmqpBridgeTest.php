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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\AmqpCompat\Bridge;

use AMQPChannel;
use AMQPConnection;
use Asmblah\PhpAmqpCompat\Bridge\AmqpBridge;
use Asmblah\PhpAmqpCompat\Bridge\Channel\AmqpChannelBridgeInterface;
use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Connection\Config\ConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;

/**
 * Class AmqpBridgeTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqpBridgeTest extends AbstractTestCase
{
    public function setUp(): void
    {
        AmqpBridge::uninitialise();
    }

    public function tearDown(): void
    {
        AmqpBridge::uninitialise();
    }

    public function testBridgeChannelBridgesCorrectly(): void
    {
        $amqpChannel = mock(AMQPChannel::class);
        $amqpChannelBridge = mock(AmqpChannelBridgeInterface::class);
        AmqpBridge::initialise();

        AmqpBridge::bridgeChannel($amqpChannel, $amqpChannelBridge);

        static::assertSame($amqpChannelBridge, AmqpBridge::getBridgeChannel($amqpChannel));
    }

    public function testBridgeConnectionBridgesCorrectly(): void
    {
        $amqpConnection = mock(AMQPConnection::class);
        $amqpConnectionBridge = mock(AmqpConnectionBridgeInterface::class);
        AmqpBridge::initialise();

        AmqpBridge::bridgeConnection($amqpConnection, $amqpConnectionBridge);

        static::assertSame($amqpConnectionBridge, AmqpBridge::getBridgeConnection($amqpConnection));
    }

    public function testBridgeConnectionConfigBridgesCorrectly(): void
    {
        $amqpConnection = mock(AMQPConnection::class);
        $connectionConfig = mock(ConnectionConfigInterface::class);
        AmqpBridge::initialise();

        AmqpBridge::bridgeConnectionConfig($amqpConnection, $connectionConfig);

        static::assertSame($connectionConfig, AmqpBridge::getConnectionConfig($amqpConnection));
    }

    public function testInitialiseDoesNotClearExistingBridgesOnReinitialisation(): void
    {
        $amqpChannel = mock(AMQPChannel::class);
        $amqpChannelBridge = mock(AmqpChannelBridgeInterface::class);
        AmqpBridge::initialise();
        AmqpBridge::bridgeChannel($amqpChannel, $amqpChannelBridge);

        AmqpBridge::initialise(); // Re-initialise.

        static::assertSame($amqpChannelBridge, AmqpBridge::getBridgeChannel($amqpChannel));
    }

    public function testIsInitialisedReturnsFalseInitially(): void
    {
        static::assertFalse(AmqpBridge::isInitialised());
    }

    public function testIsInitialisedReturnsTrueAfterInitialisation(): void
    {
        AmqpBridge::initialise();

        static::assertTrue(AmqpBridge::isInitialised());
    }

    public function testIsInitialisedReturnsFalseAfterLaterUninitialisation(): void
    {
        AmqpBridge::initialise();
        AmqpBridge::uninitialise();

        static::assertFalse(AmqpBridge::isInitialised());
    }
}
