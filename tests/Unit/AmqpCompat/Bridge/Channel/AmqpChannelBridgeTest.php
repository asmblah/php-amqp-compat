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

use AMQPChannelException;
use AMQPConnectionException;
use AMQPEnvelope;
use AMQPException;
use AMQPQueue;
use Asmblah\PhpAmqpCompat\Bridge\Channel\AmqpChannelBridge;
use Asmblah\PhpAmqpCompat\Bridge\Channel\ConsumerInterface;
use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Connection\Config\ConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Channel\ChannelInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Logger\LoggerInterface;
use Asmblah\PhpAmqpCompat\Error\ErrorReporterInterface;
use Asmblah\PhpAmqpCompat\Exception\HeartbeatMissedException;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use LogicException;
use Mockery\MockInterface;

/**
 * Class AmqpChannelBridgeTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqpChannelBridgeTest extends AbstractTestCase
{
    private MockInterface&ChannelInterface $channel;
    private AmqpChannelBridge $channelBridge;
    private MockInterface&AmqpConnectionBridgeInterface $connectionBridge;
    private MockInterface&ConsumerInterface $consumer;
    private MockInterface&ErrorReporterInterface $errorReporter;
    private MockInterface&LoggerInterface $logger;

    public function setUp(): void
    {
        $this->channel = mock(ChannelInterface::class);
        $this->errorReporter = mock(ErrorReporterInterface::class);
        $this->logger = mock(LoggerInterface::class);
        $this->connectionBridge = mock(AmqpConnectionBridgeInterface::class, [
            'getConnectionConfig' => mock(ConnectionConfigInterface::class, [
                'getReadTimeout' => 123.45,
            ]),
            'getErrorReporter' => $this->errorReporter,
            'getLogger' => $this->logger,
        ]);
        $this->consumer = mock(ConsumerInterface::class);

        $this->channelBridge = new AmqpChannelBridge(
            $this->connectionBridge,
            $this->channel,
            $this->consumer
        );
    }

    public function testAcquireChannelReturnsChannelWhenOpenAndConnected(): void
    {
        $this->channel->allows('isOpen')->andReturn(true);
        $this->channel->allows('hasConnection')->andReturn(true);
        $this->channel->allows('isConnected')->andReturn(true);
        $this->connectionBridge->allows('checkHeartbeat');

        static::assertSame($this->channel, $this->channelBridge->acquireChannel('MyError'));
    }

    public function testAcquireChannelThrowsAmqpChannelExceptionWhenChannelIsNotOpen(): void
    {
        $this->channel->allows('isOpen')->andReturn(false);

        $this->expectException(AMQPChannelException::class);
        $this->expectExceptionMessage('MyError No channel available.');

        $this->channelBridge->acquireChannel('MyError');
    }

    public function testAcquireChannelThrowsAmqpChannelExceptionWhenChannelHasNoConnection(): void
    {
        $this->channel->allows('isOpen')->andReturn(true);
        $this->channel->allows('hasConnection')->andReturn(false);

        $this->expectException(AMQPChannelException::class);
        $this->expectExceptionMessage('MyError Stale reference to the connection object.');

        $this->channelBridge->acquireChannel('MyError');
    }

    public function testAcquireChannelThrowsAmqpConnectionExceptionWhenChannelIsNotConnected(): void
    {
        $this->channel->allows('isOpen')->andReturn(true);
        $this->channel->allows('hasConnection')->andReturn(true);
        $this->channel->allows('isConnected')->andReturn(false);

        $this->expectException(AMQPConnectionException::class);
        $this->expectExceptionMessage('MyError No connection available.');

        $this->channelBridge->acquireChannel('MyError');
    }

    public function testAcquireChannelThrowsAmqpExceptionWhenHeartbeatIsMissed(): void
    {
        $this->channel->allows('isOpen')->andReturn(true);
        $this->channel->allows('hasConnection')->andReturn(true);
        $this->channel->allows('isConnected')->andReturn(true);
        $this->connectionBridge->allows('checkHeartbeat')
            ->andThrow(new HeartbeatMissedException('Heartbeat missed'));

        $this->expectException(AMQPException::class);
        $this->expectExceptionMessage('Heartbeat missed');

        $this->channelBridge->acquireChannel('MyError');
    }

    public function testCloseQuietlyDelegatesToChannel(): void
    {
        $this->channel->expects('closeQuietly')
            ->once();

        $this->channelBridge->closeQuietly();
    }

    public function testConsumeEnvelopeDelegatesToTheConsumer(): void
    {
        $amqpEnvelope = mock(AMQPEnvelope::class, [
            'getConsumerTag' => 'my_consumer_tag',
        ]);
        $amqpQueue = mock(AMQPQueue::class);
        $this->channelBridge->subscribeConsumer('my_consumer_tag', $amqpQueue);

        $this->consumer->expects()
            ->consumeEnvelope($amqpEnvelope, $amqpQueue)
            ->once();

        $this->channelBridge->consumeEnvelope($amqpEnvelope);
    }

    public function testConsumeEnvelopeThrowsWhenNoConsumerIsRegisteredForTag(): void
    {
        $amqpEnvelope = mock(AMQPEnvelope::class, [
            'getConsumerTag' => 'my_consumer_tag',
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            AmqpChannelBridge::class .
            '::consumeEnvelope(): No consumer registered for consumer tag "my_consumer_tag"'
        );

        $this->channelBridge->consumeEnvelope($amqpEnvelope);
    }

    public function testGetChannelIdDelegatesToChannel(): void
    {
        $this->channel->allows('getChannelId')
            ->andReturn(42);

        static::assertSame(42, $this->channelBridge->getChannelId());
    }

    public function testGetChannelIdReturnsNullWhenChannelIsClosed(): void
    {
        $this->channel->allows('getChannelId')
            ->andReturnNull();

        static::assertNull($this->channelBridge->getChannelId());
    }

    public function testGetConnectionBridgeReturnsTheBridge(): void
    {
        static::assertSame($this->connectionBridge, $this->channelBridge->getConnectionBridge());
    }

    public function testGetErrorReporterReturnsTheReporter(): void
    {
        static::assertSame($this->errorReporter, $this->channelBridge->getErrorReporter());
    }

    public function testGetLoggerReturnsTheLogger(): void
    {
        static::assertSame($this->logger, $this->channelBridge->getLogger());
    }

    public function testGetReadTimeoutReturnsTheTimeout(): void
    {
        static::assertSame(123.45, $this->channelBridge->getReadTimeout());
    }

    public function testGetSubscribedConsumersFetchesMapFromConsumerTagToQueue(): void
    {
        $amqpQueue1 = mock(AMQPQueue::class);
        $this->channelBridge->subscribeConsumer('my-first-consumer', $amqpQueue1);
        $amqpQueue2 = mock(AMQPQueue::class);
        $this->channelBridge->subscribeConsumer('my-second-consumer', $amqpQueue2);

        $consumers = $this->channelBridge->getSubscribedConsumers();

        static::assertCount(2, $consumers);
        static::assertSame($amqpQueue1, $consumers['my-first-consumer']);
        static::assertSame($amqpQueue2, $consumers['my-second-consumer']);
    }

    public function testIsConnectedReturnsTrueWhenHasConnectionAndIsConnected(): void
    {
        $this->channel->allows('hasConnection')->andReturn(true);
        $this->channel->allows('isConnected')->andReturn(true);

        static::assertTrue($this->channelBridge->isConnected());
    }

    public function testIsConnectedReturnsFalseWhenHasNoConnection(): void
    {
        $this->channel->allows('hasConnection')->andReturn(false);

        static::assertFalse($this->channelBridge->isConnected());
    }

    public function testIsConnectedReturnsFalseWhenHasConnectionButIsNotConnected(): void
    {
        $this->channel->allows('hasConnection')->andReturn(true);
        $this->channel->allows('isConnected')->andReturn(false);

        static::assertFalse($this->channelBridge->isConnected());
    }

    public function testIsConsumerSubscribedReturnsTrueWhenSubscribed(): void
    {
        $amqpQueue = mock(AMQPQueue::class);
        $this->channelBridge->subscribeConsumer('my_consumer_tag', $amqpQueue);

        static::assertTrue($this->channelBridge->isConsumerSubscribed('my_consumer_tag'));
    }

    public function testIsConsumerSubscribedReturnsFalseWhenNotSubscribed(): void
    {
        static::assertFalse($this->channelBridge->isConsumerSubscribed('invalid_consumer_tag'));
    }

    /**
     * @dataProvider booleanDataProvider
     */
    public function testIsOpenDelegatesToChannel(bool $value): void
    {
        $this->channel->allows('isOpen')->andReturn($value);

        static::assertSame($value, $this->channelBridge->isOpen());
    }

    public function testUnregisterChannelUnregistersChannelBridgeViaConnectionBridge(): void
    {
        $this->connectionBridge->expects()
            ->unregisterChannelBridge($this->channelBridge)
            ->once();

        $this->channelBridge->unregisterChannel();
    }

    public function testUnsubscribeConsumerUnsubscribesTheConsumer(): void
    {
        $amqpQueue1 = mock(AMQPQueue::class);
        $this->channelBridge->subscribeConsumer('my_first_consumer_tag', $amqpQueue1);
        $amqpQueue2 = mock(AMQPQueue::class);
        $this->channelBridge->subscribeConsumer('my_second_consumer_tag', $amqpQueue2);

        $this->channelBridge->unsubscribeConsumer('my_first_consumer_tag');

        static::assertFalse($this->channelBridge->isConsumerSubscribed('my_first_consumer_tag'));
        static::assertTrue($this->channelBridge->isConsumerSubscribed('my_second_consumer_tag'));
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
