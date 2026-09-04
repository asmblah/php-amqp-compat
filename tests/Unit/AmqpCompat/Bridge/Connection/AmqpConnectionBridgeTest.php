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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\AmqpCompat\Bridge\Connection;

use AMQPChannelException;
use AMQPConnectionException;
use Asmblah\PhpAmqpCompat\Bridge\Channel\AmqpChannelBridge;
use Asmblah\PhpAmqpCompat\Bridge\Channel\AmqpChannelBridgeInterface;
use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridge;
use Asmblah\PhpAmqpCompat\Connection\Config\ConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Channel\ChannelInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Logger\LoggerInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Transport\TransportInterface;
use Asmblah\PhpAmqpCompat\Error\ErrorReporterInterface;
use Asmblah\PhpAmqpCompat\Exception\TooManyChannelsOnConnectionException;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Mockery\MockInterface;

/**
 * Class AmqpConnectionBridgeTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqpConnectionBridgeTest extends AbstractTestCase
{
    private AmqpConnectionBridge $connectionBridge;
    private MockInterface&ConnectionConfigInterface $connectionConfig;
    private MockInterface&ErrorReporterInterface $errorReporter;
    private MockInterface&LoggerInterface $logger;
    private MockInterface&TransportInterface $transport;

    public function setUp(): void
    {
        $this->connectionConfig = mock(ConnectionConfigInterface::class);
        $this->errorReporter = mock(ErrorReporterInterface::class);
        $this->logger = mock(LoggerInterface::class);
        $this->transport = mock(TransportInterface::class);

        $this->connectionBridge = new AmqpConnectionBridge(
            $this->transport,
            $this->connectionConfig,
            $this->errorReporter,
            $this->logger
        );
    }

    public function testCheckHeartbeatDelegatesToTransport(): void
    {
        $this->transport->expects('checkHeartbeat')
            ->once();

        $this->connectionBridge->checkHeartbeat();
    }

    public function testCreateChannelBridgeOpensAChannelViaTransport(): void
    {
        $this->transport->expects()
            ->openChannel(AMQPChannelException::class, 'MyClass::myMethod')
            ->once()
            ->andReturn(mock(ChannelInterface::class));

        $this->connectionBridge->createChannelBridge(AMQPChannelException::class, 'MyClass::myMethod');
    }

    public function testCreateChannelBridgeReturnsTheCreatedBridge(): void
    {
        $this->transport->allows()
            ->openChannel(AMQPChannelException::class, 'MyClass::myMethod')
            ->andReturn(mock(ChannelInterface::class));

        static::assertInstanceOf(
            AmqpChannelBridge::class,
            $this->connectionBridge->createChannelBridge(AMQPChannelException::class, 'MyClass::myMethod')
        );
    }

    public function testCreateChannelBridgeDoesNotRaiseTooManyChannelsOnConnectionExceptionWhenAtLimit(): void
    {
        $this->transport->allows()
            ->openChannel(AMQPChannelException::class, 'MyClass::myMethod')
            ->andReturn(mock(ChannelInterface::class));
        /** @var AmqpChannelBridgeInterface[] $channelBridges */
        $channelBridges = [];

        for ($i = 0; $i < PHP_AMQP_MAX_CHANNELS; $i++) {
            $channelBridges[] = $this->connectionBridge->createChannelBridge(AMQPChannelException::class, 'MyClass::myMethod');
        }

        static::assertCount(PHP_AMQP_MAX_CHANNELS, $channelBridges);
    }

    public function testCreateChannelBridgeRaisesTooManyChannelsOnConnectionExceptionWhenAlreadyAtLimit(): void
    {
        $this->transport->allows()
            ->openChannel(AMQPChannelException::class, 'MyClass::myMethod')
            ->andReturn(mock(ChannelInterface::class));
        /** @var AmqpChannelBridgeInterface[] $channelBridges */
        /** @noinspection PhpArrayUsedOnlyForWriteInspection */
        $channelBridges = [];

        $this->expectException(TooManyChannelsOnConnectionException::class);
        $this->expectExceptionMessage('Connection already has 256 channels open');

        for ($i = 0; $i < PHP_AMQP_MAX_CHANNELS + 1; $i++) {
            // Keep a reference to each object to avoid them being freed too early.
            $channelBridges[] = $this->connectionBridge->createChannelBridge(AMQPChannelException::class, 'MyClass::myMethod');
        }
    }

    public function testDisconnectDelegatesToTransport(): void
    {
        $this->transport->expects()
            ->disconnect(AMQPConnectionException::class, 'AMQPConnection::disconnect')
            ->once();

        $this->connectionBridge->disconnect(AMQPConnectionException::class, 'AMQPConnection::disconnect');
    }

    public function testGetConnectionConfigReturnsTheConfig(): void
    {
        static::assertSame($this->connectionConfig, $this->connectionBridge->getConnectionConfig());
    }

    public function testGetErrorReporterReturnsTheErrorReporter(): void
    {
        static::assertSame($this->errorReporter, $this->connectionBridge->getErrorReporter());
    }

    public function testGetHeartbeatIntervalDelegatesToTransport(): void
    {
        $this->transport->allows()
            ->getHeartbeatInterval()
            ->andReturn(21);

        static::assertSame(21, $this->connectionBridge->getHeartbeatInterval());
    }

    public function testGetLoggerReturnsTheLogger(): void
    {
        static::assertSame($this->logger, $this->connectionBridge->getLogger());
    }

    public function testGetTransportReturnsTheTransport(): void
    {
        static::assertSame($this->transport, $this->connectionBridge->getTransport());
    }

    public function testGetUsedChannelsReturnsZeroInitially(): void
    {
        static::assertSame(0, $this->connectionBridge->getUsedChannels());
    }

    public function testGetUsedChannelsReturnsOneAfterCreatingAChannelBridge(): void
    {
        $this->transport->allows()
            ->openChannel(AMQPChannelException::class, 'MyClass::myMethod')
            ->andReturn(mock(ChannelInterface::class));

        $this->connectionBridge->createChannelBridge(AMQPChannelException::class, 'MyClass::myMethod');

        static::assertSame(1, $this->connectionBridge->getUsedChannels());
    }

    /**
     * @dataProvider booleanDataProvider
     */
    public function testIsBusyDelegatesToTransport(bool $value): void
    {
        $this->transport->allows()
            ->isBusy()
            ->andReturn($value);

        static::assertSame($value, $this->connectionBridge->isBusy());
    }

    /**
     * @dataProvider booleanDataProvider
     */
    public function testIsConnectedDelegatesToTransport(bool $value): void
    {
        $this->transport->allows()
            ->isConnected()
            ->andReturn($value);

        static::assertSame($value, $this->connectionBridge->isConnected());
    }

    public function testSetReadTimeoutSetsViaTheTransport(): void
    {
        $this->transport->expects()
            ->setReadTimeout(21.05)
            ->once();

        $this->connectionBridge->setReadTimeout(21.05);
    }

    public function testUnregisterChannelBridgeUnregisters(): void
    {
        $this->transport->allows()
            ->openChannel(AMQPChannelException::class, 'MyClass::myMethod')
            ->andReturn(mock(ChannelInterface::class));
        $channelBridge = $this->connectionBridge->createChannelBridge(AMQPChannelException::class, 'MyClass::myMethod');

        $this->connectionBridge->unregisterChannelBridge($channelBridge);

        static::assertSame(0, $this->connectionBridge->getUsedChannels());
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function booleanDataProvider(): array
    {
        return [
            'true' => [true],
            'false' => [false],
        ];
    }
}
