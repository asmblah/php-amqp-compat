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

use Asmblah\PhpAmqpCompat\Bridge\Channel\AmqpChannelBridge;
use Asmblah\PhpAmqpCompat\Bridge\Channel\EnvelopeTransformerInterface;
use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridge;
use Asmblah\PhpAmqpCompat\Error\ErrorReporterInterface;
use Asmblah\PhpAmqpCompat\Logger\LoggerInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Mockery\MockInterface;
use PhpAmqpLib\Channel\AMQPChannel as AmqplibChannel;
use PhpAmqpLib\Connection\AbstractConnection as AmqplibConnection;

/**
 * Class AmqpConnectionBridgeTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqpConnectionBridgeTest extends AbstractTestCase
{
    private MockInterface&AmqplibConnection $amqplibConnection;
    private AmqpConnectionBridge $connectionBridge;
    private MockInterface&EnvelopeTransformerInterface $envelopeTransformer;
    private MockInterface&ErrorReporterInterface $errorReporter;
    private MockInterface&LoggerInterface $logger;

    public function setUp(): void
    {
        $this->amqplibConnection = mock(AmqplibConnection::class);
        $this->envelopeTransformer = mock(EnvelopeTransformerInterface::class);
        $this->errorReporter = mock(ErrorReporterInterface::class);
        $this->logger = mock(LoggerInterface::class);

        $this->connectionBridge = new AmqpConnectionBridge(
            $this->amqplibConnection,
            $this->envelopeTransformer,
            $this->errorReporter,
            $this->logger
        );
    }

    public function testCreateChannelBridgeCreatesAChannelViaAmqplibConnection(): void
    {
        $amqplibChannel = mock(AmqplibChannel::class);
        $this->amqplibConnection->expects()
            ->channel()
            ->once()
            ->andReturn($amqplibChannel);

        $this->connectionBridge->createChannelBridge();
    }

    public function testCreateChannelBridgeReturnsTheCreatedBridge(): void
    {
        $this->amqplibConnection->allows()
            ->channel()
            ->andReturn(mock(AmqplibChannel::class));

        static::assertInstanceOf(AmqpChannelBridge::class, $this->connectionBridge->createChannelBridge());
    }

    public function testGetAmqplibConnectionReturnsTheUnderlyingConnection(): void
    {
        static::assertSame($this->amqplibConnection, $this->connectionBridge->getAmqplibConnection());
    }

    public function testGetEnvelopeTransformerReturnsTheTransformer(): void
    {
        static::assertSame($this->envelopeTransformer, $this->connectionBridge->getEnvelopeTransformer());
    }

    public function testGetErrorReporterReturnsTheErrorReporter(): void
    {
        static::assertSame($this->errorReporter, $this->connectionBridge->getErrorReporter());
    }

    public function testGetHeartbeatIntervalReturnsHalfTheIntervalFromAmqplib(): void
    {
        $this->amqplibConnection->allows()
            ->getHeartbeat()
            ->andReturn(42);

        static::assertSame(21, $this->connectionBridge->getHeartbeatInterval());
    }

    public function testGetLoggerReturnsTheLogger(): void
    {
        static::assertSame($this->logger, $this->connectionBridge->getLogger());
    }

    public function testGetUsedChannelsReturnsZeroInitially(): void
    {
        static::assertSame(0, $this->connectionBridge->getUsedChannels());
    }

    public function testGetUsedChannelsReturnsOneAfterCreatingAChannelBridge(): void
    {
        $this->amqplibConnection->allows()
            ->channel()
            ->andReturn(mock(AmqplibChannel::class));

        $this->connectionBridge->createChannelBridge();

        static::assertSame(1, $this->connectionBridge->getUsedChannels());
    }

    public function testUnregisterChannelBridgeUnregisters(): void
    {
        $this->amqplibConnection->allows()
            ->channel()
            ->andReturn(mock(AmqplibChannel::class));
        $channelBridge = $this->connectionBridge->createChannelBridge();

        $this->connectionBridge->unregisterChannelBridge($channelBridge);

        static::assertSame(0, $this->connectionBridge->getUsedChannels());
    }
}
