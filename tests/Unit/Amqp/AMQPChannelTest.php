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

use AMQPChannel;
use AMQPChannelException;
use AMQPConnection;
use AMQPQueue;
use Asmblah\PhpAmqpCompat\Bridge\AmqpBridge;
use Asmblah\PhpAmqpCompat\Bridge\Channel\AmqpChannelBridgeInterface;
use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Connection\Config\ConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Channel\ChannelInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Logger\LoggerInterface;
use Asmblah\PhpAmqpCompat\Exception\TooManyChannelsOnConnectionException;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use LogicException;
use Mockery\MockInterface;

/**
 * Class AMQPChannelTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AMQPChannelTest extends AbstractTestCase
{
    private ?AMQPChannel $amqpChannel;
    private MockInterface&AMQPConnection $amqpConnection;
    private MockInterface&ChannelInterface $channel;
    private MockInterface&AmqpChannelBridgeInterface $channelBridge;
    private MockInterface&ConnectionConfigInterface $connectionConfig;
    private MockInterface&AmqpConnectionBridgeInterface $connectionBridge;
    private MockInterface&LoggerInterface $logger;

    public function setUp(): void
    {
        $this->amqpConnection = mock(AMQPConnection::class, [
            'isConnected' => true,
        ]);
        $this->channel = mock(ChannelInterface::class, [
            'basicQos' => null,
        ]);
        $this->channelBridge = mock(AmqpChannelBridgeInterface::class, [
            'acquireChannel' => $this->channel,
            'closeQuietly' => null,
            'getChannelId' => 12345,
            'isConnected' => true,
            'isOpen' => true,
            'getSubscribedConsumers' => [
                'consumer-tag-1' => mock(AMQPQueue::class, [
                    'getName' => 'my_queue_1',
                ]),
                'consumer-tag-2' => mock(AMQPQueue::class, [
                    'getName' => 'my_queue_2',
                ]),
            ],
            'unregisterChannel' => null,
        ]);
        $this->connectionConfig = mock(ConnectionConfigInterface::class, [
            'getGlobalPrefetchCount' => 100,
            'getGlobalPrefetchSize' => 512,
            'getPrefetchCount' => 50,
            'getPrefetchSize' => 128,
        ]);
        $this->logger = mock(LoggerInterface::class, [
            'debug' => null,
            'warning' => null,
        ]);
        $this->connectionBridge = mock(AmqpConnectionBridgeInterface::class, [
            'createChannelBridge' => $this->channelBridge,
            'getConnectionConfig' => $this->connectionConfig,
            'getLogger' => $this->logger,
        ]);

        AmqpBridge::bridgeConnection($this->amqpConnection, $this->connectionBridge);

        $this->amqpChannel = new AMQPChannel($this->amqpConnection);
    }

    public function testConstructorNotBeingCalledIsHandledCorrectly(): void
    {
        $extendedAmqpChannel = new class extends AMQPChannel {
            public function __construct()
            {
                // Deliberately omit the call to the super constructor.
            }
        };

        $this->expectException(AMQPChannelException::class);
        $this->expectExceptionMessage('Could not start the transaction. Stale reference to the channel object.');

        $extendedAmqpChannel->startTransaction();
    }

    public function testConstructorCorrectlyBridgesTheChannelToTheCreatedChannelBridge(): void
    {
        $this->connectionBridge->expects()
            ->createChannelBridge(AMQPChannelException::class, 'AMQPChannel::__construct')
            ->once()
            ->andReturn($this->channelBridge);

        new AMQPChannel($this->amqpConnection);

        static::assertSame($this->channelBridge, AmqpBridge::getBridgeChannel($this->amqpChannel));
    }

    public function testConstructorSetsPrefetchSettingsWhenGlobalAreNonZero(): void
    {
        $this->channel->expects()
            ->basicQos(128, 50, false, AMQPChannelException::class, 'AMQPChannel::__construct')
            ->once()
            ->globally()->ordered();
        $this->channel->expects()
            ->basicQos(512, 100, true, AMQPChannelException::class, 'AMQPChannel::__construct')
            ->once()
            ->globally()->ordered(); // Global must be configured last.

        new AMQPChannel($this->amqpConnection);
    }

    public function testConstructorSetsPrefetchSettingsWhenGlobalAreZero(): void
    {
        $this->connectionConfig->allows('getGlobalPrefetchCount')
            ->andReturn(0);
        $this->connectionConfig->allows('getGlobalPrefetchSize')
            ->andReturn(0);

        $this->channel->expects()
            ->basicQos(128, 50, false, AMQPChannelException::class, 'AMQPChannel::__construct')
            ->once()
            ->globally()->ordered();
        $this->channel->expects('basicQos')
            ->never()
            ->withArgs(fn ($size, $count, $global) => $global === true)
            ->globally()->ordered(); // Global must not be configured.

        new AMQPChannel($this->amqpConnection);
    }

    public function testConstructorRaisesAmqpChannelExceptionOnTooManyChannelsOnConnectionException(): void
    {
        $this->connectionBridge->allows('createChannelBridge')
            ->andThrow(new TooManyChannelsOnConnectionException());

        $this->expectException(AMQPChannelException::class);
        $this->expectExceptionMessage('Could not create channel. Connection has no open channel slots remaining.');

        new AMQPChannel($this->amqpConnection);
    }

    public function testDestructorClosesChannelQuietly(): void
    {
        $this->channelBridge->expects()
            ->closeQuietly()
            ->once();

        $this->amqpChannel = null; // Invoke the destructor synchronously (assuming no reference cycles).
    }

    public function testDestructorUnregistersChannel(): void
    {
        $this->channelBridge->expects()
            ->unregisterChannel()
            ->once();

        $this->amqpChannel = null; // Invoke the destructor synchronously (assuming no reference cycles).
    }

    /**
     * @dataProvider basicRecoverDataProvider
     */
    public function testBasicRecoverLogsAttemptAsDebug(bool $requeue): void
    {
        $this->channel->allows()
            ->basicRecover($requeue, AMQPChannelException::class, 'AMQPChannel::basicRecover');

        $this->logger->expects()
            ->debug('AMQPChannel::basicRecover(): Recovery attempt', [
                'requeue' => $requeue,
            ])
            ->once();

        $this->amqpChannel->basicRecover($requeue);
    }

    /**
     * @dataProvider basicRecoverDataProvider
     */
    public function testBasicRecoverGoesViaChannel(bool $requeue): void
    {
        $this->channel->expects()
            ->basicRecover($requeue, AMQPChannelException::class, 'AMQPChannel::basicRecover')
            ->once();

        static::assertTrue($this->amqpChannel->basicRecover($requeue));
    }

    public function testBasicRecoverHandlesExceptionCorrectly(): void
    {
        $this->channel->allows()
            ->basicRecover(true, AMQPChannelException::class, 'AMQPChannel::basicRecover')
            ->andThrow(new AMQPChannelException('my text'));

        $this->expectException(AMQPChannelException::class);
        $this->expectExceptionMessage('my text');

        $this->amqpChannel->basicRecover();
    }

    /**
     * @return array<array<bool>>
     */
    public static function basicRecoverDataProvider(): array
    {
        return [[true], [false]];
    }

    public function testBasicRecoverLogsSuccessAsDebug(): void
    {
        $this->channel->allows()
            ->basicRecover(true, AMQPChannelException::class, 'AMQPChannel::basicRecover');

        $this->logger->expects()
            ->debug('AMQPChannel::basicRecover(): Recovered')
            ->once();

        $this->amqpChannel->basicRecover();
    }

    public function testCloseLogsAttemptAsDebug(): void
    {
        $this->logger->expects()
            ->debug('AMQPChannel::close(): Channel close attempt')
            ->once();
        $this->logger->expects()
            ->debug('AMQPChannel::close(): Closing channel', [
                'id' => 12345,
            ])
            ->once();

        $this->amqpChannel->close();
    }

    public function testCloseClosesChannelQuietlyViaChannelBridge(): void
    {
        $this->channelBridge->expects()
            ->closeQuietly()
            ->once();

        $this->amqpChannel->close();
    }

    public function testCloseHandlesConstructorNotBeingCalledCorrectly(): void
    {
        $extendedAmqpChannel = new class extends AMQPChannel {
            public function __construct()
            {
                // Deliberately omit the call to the super constructor.
            }
        };

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('AMQPChannel::close(): Invalid channel; constructor was never called');

        $extendedAmqpChannel->close();
    }

    public function testCloseHandlesChannelAlreadyBeingClosedCorrectly(): void
    {
        $this->channelBridge->allows()
            ->isOpen()
            ->andReturn(false);

        $this->logger->expects()
            ->debug('AMQPChannel::close(): Channel already closed')
            ->once();
        $this->channelBridge->expects()
            ->closeQuietly()
            ->never();

        $this->amqpChannel->close();
    }

    public function testCloseLogsWarningWhenConnectionIsAlreadyClosed(): void
    {
        $this->channelBridge->allows()
            ->isConnected()
            ->andReturn(false);

        $this->logger->expects()
            ->warning('AMQPChannel::close(): Underlying connection has already been closed')
            ->once();

        $this->amqpChannel->close();
    }

    public function testCloseLogsSuccessAsDebug(): void
    {
        $this->logger->expects()
            ->debug('AMQPChannel::close(): Channel closed')
            ->once();

        $this->amqpChannel->close();
    }

    public function testCommitTransactionLogsAttemptAsDebug(): void
    {
        $this->channel->allows()
            ->commitTransaction(AMQPChannelException::class, 'AMQPChannel::commitTransaction');

        $this->logger->expects()
            ->debug('AMQPChannel::commitTransaction(): Transaction commit attempt')
            ->once();

        $this->amqpChannel->commitTransaction();
    }

    public function testCommitTransactionGoesViaChannel(): void
    {
        $this->channel->expects()
            ->commitTransaction(AMQPChannelException::class, 'AMQPChannel::commitTransaction')
            ->once();

        static::assertTrue($this->amqpChannel->commitTransaction());
    }

    public function testCommitTransactionHandlesExceptionCorrectly(): void
    {
        $this->channel->allows()
            ->commitTransaction(AMQPChannelException::class, 'AMQPChannel::commitTransaction')
            ->andThrow(new AMQPChannelException('my text'));

        $this->expectException(AMQPChannelException::class);
        $this->expectExceptionMessage('my text');

        $this->amqpChannel->commitTransaction();
    }

    public function testCommitTransactionLogsSuccessAsDebug(): void
    {
        $this->channel->allows()
            ->commitTransaction(AMQPChannelException::class, 'AMQPChannel::commitTransaction');

        $this->logger->expects()
            ->debug('AMQPChannel::commitTransaction(): Transaction committed')
            ->once();

        $this->amqpChannel->commitTransaction();
    }

    public function testGetChannelIdDelegatesToChannelBridge(): void
    {
        static::assertSame(12345, $this->amqpChannel->getChannelId());
    }

    public function testGetChannelIdReturnsNullWhenChannelIsClosed(): void
    {
        $this->channelBridge->allows('getChannelId')
            ->andReturnNull();

        static::assertNull($this->amqpChannel->getChannelId());
    }

    public function testGetConsumersFetchesSubscribedConsumers(): void
    {
        $consumers = $this->amqpChannel->getConsumers();

        static::assertCount(2, $consumers);
        static::assertInstanceOf(AMQPQueue::class, $consumers['consumer-tag-1']);
        static::assertSame('my_queue_1', $consumers['consumer-tag-1']->getName());
        static::assertInstanceOf(AMQPQueue::class, $consumers['consumer-tag-2']);
        static::assertSame('my_queue_2', $consumers['consumer-tag-2']->getName());
    }

    /**
     * @dataProvider booleanDataProvider
     */
    public function testIsConnectedDelegatesToChannelBridge(bool $connected): void
    {
        $this->channelBridge->allows('isConnected')
            ->andReturn($connected);

        static::assertSame($connected, $this->amqpChannel->isConnected());
    }

    /**
     * @dataProvider qosDataProvider
     */
    public function testQosLogsAttemptAsDebug(int $prefetchSize, int $prefetchCount, bool $global): void
    {
        $this->channel->allows()
            ->basicQos($prefetchSize, $prefetchCount, $global, AMQPChannelException::class, 'AMQPChannel::qos');

        $this->logger->expects()
            ->debug('AMQPChannel::qos(): QOS setting change attempt', [
                'count' => $prefetchCount,
                'global' => $global,
                'size' => $prefetchSize,
            ])
            ->once();

        $this->amqpChannel->qos($prefetchSize, $prefetchCount, $global);
    }

    /**
     * @dataProvider qosDataProvider
     */
    public function testQosGoesViaChannel(int $prefetchSize, int $prefetchCount, bool $global): void
    {
        $this->channel->expects()
            ->basicQos($prefetchSize, $prefetchCount, $global, AMQPChannelException::class, 'AMQPChannel::qos')
            ->once();

        static::assertTrue($this->amqpChannel->qos($prefetchSize, $prefetchCount, $global));
    }

    public function testQosDefaultsGlobalToFalse(): void
    {
        $this->channel->expects()
            ->basicQos(256, 21, false, AMQPChannelException::class, 'AMQPChannel::qos')
            ->once();

        static::assertTrue($this->amqpChannel->qos(256, 21));
    }

    /**
     * @dataProvider qosDataProvider
     */
    public function testQosHandlesExceptionCorrectly(int $prefetchSize, int $prefetchCount, bool $global): void
    {
        $this->channel->allows()
            ->basicQos($prefetchSize, $prefetchCount, $global, AMQPChannelException::class, 'AMQPChannel::qos')
            ->andThrow(new AMQPChannelException('my text'));

        $this->expectException(AMQPChannelException::class);
        $this->expectExceptionMessage('my text');

        $this->amqpChannel->qos($prefetchSize, $prefetchCount, $global);
    }

    public function testQosLogsSuccessAsDebug(): void
    {
        $this->channel->allows()
            ->basicQos(512, 20, false, AMQPChannelException::class, 'AMQPChannel::qos');

        $this->logger->expects()
            ->debug('AMQPChannel::qos(): QOS settings changed')
            ->once();

        $this->amqpChannel->qos(512, 20);
    }

    public function testRollbackTransactionLogsAttemptAsDebug(): void
    {
        $this->channel->allows()
            ->rollbackTransaction(AMQPChannelException::class, 'AMQPChannel::rollbackTransaction');

        $this->logger->expects()
            ->debug('AMQPChannel::rollbackTransaction(): Transaction rollback attempt')
            ->once();

        $this->amqpChannel->rollbackTransaction();
    }

    public function testRollbackTransactionGoesViaChannel(): void
    {
        $this->channel->expects()
            ->rollbackTransaction(AMQPChannelException::class, 'AMQPChannel::rollbackTransaction')
            ->once();

        static::assertTrue($this->amqpChannel->rollbackTransaction());
    }

    public function testRollbackTransactionHandlesExceptionCorrectly(): void
    {
        $this->channel->allows()
            ->rollbackTransaction(AMQPChannelException::class, 'AMQPChannel::rollbackTransaction')
            ->andThrow(new AMQPChannelException('my text'));

        $this->expectException(AMQPChannelException::class);
        $this->expectExceptionMessage('my text');

        $this->amqpChannel->rollbackTransaction();
    }

    public function testRollbackTransactionLogsSuccessAsDebug(): void
    {
        $this->channel->allows()
            ->rollbackTransaction(AMQPChannelException::class, 'AMQPChannel::rollbackTransaction');

        $this->logger->expects()
            ->debug('AMQPChannel::rollbackTransaction(): Transaction rolled back')
            ->once();

        $this->amqpChannel->rollbackTransaction();
    }

    public function testSetGlobalPrefetchCountLogsAttemptAsDebug(): void
    {
        $this->channel->allows()
            ->basicQos(0, 100, true, AMQPChannelException::class, 'AMQPChannel::setGlobalPrefetchCount');

        $this->logger->expects()
            ->debug('AMQPChannel::setGlobalPrefetchCount(): Global prefetch count change attempt', [
                'count' => 100,
            ])
            ->once();

        $this->amqpChannel->setGlobalPrefetchCount(100);
    }

    public function testSetGlobalPrefetchCountGoesViaChannel(): void
    {
        $this->channel->expects()
            ->basicQos(0, 20, true, AMQPChannelException::class, 'AMQPChannel::setGlobalPrefetchCount')
            ->once();

        static::assertTrue($this->amqpChannel->setGlobalPrefetchCount(20));
    }

    public function testSetGlobalPrefetchCountHandlesExceptionCorrectly(): void
    {
        $this->channel->allows()
            ->basicQos(0, 10, true, AMQPChannelException::class, 'AMQPChannel::setGlobalPrefetchCount')
            ->andThrow(new AMQPChannelException('my text'));

        $this->expectException(AMQPChannelException::class);
        $this->expectExceptionMessage('my text');

        $this->amqpChannel->setGlobalPrefetchCount(10);
    }

    public function testSetGlobalPrefetchCountLogsSuccessAsDebug(): void
    {
        $this->channel->allows()
            ->basicQos(0, 100, true, AMQPChannelException::class, 'AMQPChannel::setGlobalPrefetchCount');

        $this->logger->expects()
            ->debug('AMQPChannel::setGlobalPrefetchCount(): Global prefetch count changed')
            ->once();

        $this->amqpChannel->setGlobalPrefetchCount(100);
    }

    public function testSetGlobalPrefetchSizeLogsAttemptAsDebug(): void
    {
        $this->channel->allows()
            ->basicQos(128, 0, true, AMQPChannelException::class, 'AMQPChannel::setGlobalPrefetchSize');

        $this->logger->expects()
            ->debug('AMQPChannel::setGlobalPrefetchSize(): Global prefetch size change attempt', [
                'size' => 128,
            ])
            ->once();

        $this->amqpChannel->setGlobalPrefetchSize(128);
    }

    public function testSetGlobalPrefetchSizeGoesViaChannel(): void
    {
        $this->channel->expects()
            ->basicQos(128, 0, true, AMQPChannelException::class, 'AMQPChannel::setGlobalPrefetchSize')
            ->once();

        static::assertTrue($this->amqpChannel->setGlobalPrefetchSize(128));
    }

    public function testSetGlobalPrefetchSizeHandlesExceptionCorrectly(): void
    {
        $this->channel->allows()
            ->basicQos(64, 0, true, AMQPChannelException::class, 'AMQPChannel::setGlobalPrefetchSize')
            ->andThrow(new AMQPChannelException('my text'));

        $this->expectException(AMQPChannelException::class);
        $this->expectExceptionMessage('my text');

        $this->amqpChannel->setGlobalPrefetchSize(64);
    }

    public function testSetGlobalPrefetchSizeLogsSuccessAsDebug(): void
    {
        $this->channel->allows()
            ->basicQos(512, 0, true, AMQPChannelException::class, 'AMQPChannel::setGlobalPrefetchSize');

        $this->logger->expects()
            ->debug('AMQPChannel::setGlobalPrefetchSize(): Global prefetch size changed')
            ->once();

        $this->amqpChannel->setGlobalPrefetchSize(512);
    }

    public function testSetPrefetchCountLogsAttemptAsDebug(): void
    {
        $this->channel->allows()
            ->basicQos(0, 7, false, AMQPChannelException::class, 'AMQPChannel::setPrefetchCount');
        $this->channel->allows()
            ->basicQos(512, 100, true, AMQPChannelException::class, 'AMQPChannel::setPrefetchCount');

        $this->logger->expects()
            ->debug('AMQPChannel::setPrefetchCount(): Non-global prefetch count change attempt', [
                'count' => 7,
            ])
            ->once();

        $this->amqpChannel->setPrefetchCount(7);
    }

    public function testSetPrefetchCountGoesViaChannel(): void
    {
        $this->channel->expects()
            ->basicQos(0, 8, false, AMQPChannelException::class, 'AMQPChannel::setPrefetchCount')
            ->once();
        // Global settings must be re-applied.
        $this->channel->expects()
            ->basicQos(512, 100, true, AMQPChannelException::class, 'AMQPChannel::setPrefetchCount')
            ->once();

        static::assertTrue($this->amqpChannel->setPrefetchCount(8));
    }

    public function testSetPrefetchCountHandlesExceptionCorrectly(): void
    {
        $this->channel->allows()
            ->basicQos(0, 6, false, AMQPChannelException::class, 'AMQPChannel::setPrefetchCount')
            ->andThrow(new AMQPChannelException('my text'));
        $this->channel->allows()
            ->basicQos(512, 100, true, AMQPChannelException::class, 'AMQPChannel::setPrefetchCount');

        $this->expectException(AMQPChannelException::class);
        $this->expectExceptionMessage('my text');

        $this->amqpChannel->setPrefetchCount(6);
    }

    public function testSetPrefetchCountLogsSuccessAsDebug(): void
    {
        $this->channel->allows()
            ->basicQos(0, 7, false, AMQPChannelException::class, 'AMQPChannel::setPrefetchCount');
        $this->channel->allows()
            ->basicQos(512, 100, true, AMQPChannelException::class, 'AMQPChannel::setPrefetchCount');

        $this->logger->expects()
            ->debug('AMQPChannel::setPrefetchCount(): Non-global prefetch count changed')
            ->once();

        $this->amqpChannel->setPrefetchCount(7);
    }

    public function testSetPrefetchSizeLogsAttemptAsDebug(): void
    {
        $this->channel->allows()
            ->basicQos(128, 0, false, AMQPChannelException::class, 'AMQPChannel::setPrefetchSize');
        $this->channel->allows()
            ->basicQos(512, 100, true, AMQPChannelException::class, 'AMQPChannel::setPrefetchSize');

        $this->logger->expects()
            ->debug('AMQPChannel::setPrefetchSize(): Non-global prefetch size change attempt', [
                'size' => 128,
            ])
            ->once();

        $this->amqpChannel->setPrefetchSize(128);
    }

    public function testSetPrefetchSizeGoesViaChannel(): void
    {
        $this->channel->expects()
            ->basicQos(128, 0, false, AMQPChannelException::class, 'AMQPChannel::setPrefetchSize')
            ->once();
        // Global settings must be re-applied.
        $this->channel->expects()
            ->basicQos(512, 100, true, AMQPChannelException::class, 'AMQPChannel::setPrefetchSize')
            ->once();

        static::assertTrue($this->amqpChannel->setPrefetchSize(128));
    }

    public function testSetPrefetchSizeHandlesExceptionCorrectly(): void
    {
        $this->channel->allows()
            ->basicQos(64, 0, false, AMQPChannelException::class, 'AMQPChannel::setPrefetchSize')
            ->andThrow(new AMQPChannelException('my text'));
        $this->channel->allows()
            ->basicQos(512, 100, true, AMQPChannelException::class, 'AMQPChannel::setPrefetchSize');

        $this->expectException(AMQPChannelException::class);
        $this->expectExceptionMessage('my text');

        $this->amqpChannel->setPrefetchSize(64);
    }

    public function testSetPrefetchSizeLogsSuccessAsDebug(): void
    {
        $this->channel->allows()
            ->basicQos(64, 0, false, AMQPChannelException::class, 'AMQPChannel::setPrefetchSize');
        $this->channel->allows()
            ->basicQos(512, 100, true, AMQPChannelException::class, 'AMQPChannel::setPrefetchSize');

        $this->logger->expects()
            ->debug('AMQPChannel::setPrefetchSize(): Non-global prefetch size changed')
            ->once();

        $this->amqpChannel->setPrefetchSize(64);
    }

    public function testStartTransactionLogsAttemptAsDebug(): void
    {
        $this->channel->allows()
            ->startTransaction(AMQPChannelException::class, 'AMQPChannel::startTransaction');

        $this->logger->expects()
            ->debug('AMQPChannel::startTransaction(): Transaction start attempt')
            ->once();

        $this->amqpChannel->startTransaction();
    }

    public function testStartTransactionGoesViaChannel(): void
    {
        $this->channel->expects()
            ->startTransaction(AMQPChannelException::class, 'AMQPChannel::startTransaction')
            ->once();

        static::assertTrue($this->amqpChannel->startTransaction());
    }

    public function testStartTransactionHandlesExceptionCorrectly(): void
    {
        $this->channel->allows()
            ->startTransaction(AMQPChannelException::class, 'AMQPChannel::startTransaction')
            ->andThrow(new AMQPChannelException('my text'));

        $this->expectException(AMQPChannelException::class);
        $this->expectExceptionMessage('my text');

        $this->amqpChannel->startTransaction();
    }

    public function testStartTransactionLogsSuccessAsDebug(): void
    {
        $this->channel->allows()
            ->startTransaction(AMQPChannelException::class, 'AMQPChannel::startTransaction');

        $this->logger->expects()
            ->debug('AMQPChannel::startTransaction(): Transaction started')
            ->once();

        $this->amqpChannel->startTransaction();
    }

    /**
     * @return array<array{bool}>
     */
    public static function booleanDataProvider(): array
    {
        return [
            'true' => [true],
            'false' => [false],
        ];
    }

    /**
     * @return array<array<mixed>>
     */
    public static function qosDataProvider(): array
    {
        return [
            [123, 456, true],
            [3, 7, false],
        ];
    }
}
