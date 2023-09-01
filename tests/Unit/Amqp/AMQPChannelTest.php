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
use Asmblah\PhpAmqpCompat\Bridge\AmqpBridge;
use Asmblah\PhpAmqpCompat\Bridge\Channel\AmqpChannelBridgeInterface;
use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Logger\LoggerInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use LogicException;
use Mockery\MockInterface;
use PhpAmqpLib\Channel\AMQPChannel as AmqplibChannel;
use PhpAmqpLib\Connection\AbstractConnection as AmqplibConnection;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;

/**
 * Class AMQPChannelTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AMQPChannelTest extends AbstractTestCase
{
    private ?AMQPChannel $amqpChannel;
    /**
     * @var (MockInterface&AMQPConnection)|null
     */
    private $amqpConnection;
    /**
     * @var (MockInterface&AmqplibChannel)|null
     */
    private $amqplibChannel;
    /**
     * @var (MockInterface&AmqplibConnection)|null
     */
    private $amqplibConnection;
    /**
     * @var (MockInterface&AmqpChannelBridgeInterface)|null
     */
    private $channelBridge;
    /**
     * @var (MockInterface&AmqpConnectionBridgeInterface)|null
     */
    private $connectionBridge;
    /**
     * @var (MockInterface&LoggerInterface)|null
     */
    private $logger;

    public function setUp(): void
    {
        $this->amqpConnection = mock(AMQPConnection::class, [
            'isConnected' => true,
        ]);
        $this->amqplibChannel = mock(AmqplibChannel::class, [
            'close' => null,
            'getChannelId' => 12345,
            'getConnection' => $this->amqpConnection,
            'is_open' => true,
        ]);
        $this->amqplibConnection = mock(AmqplibConnection::class);
        $this->channelBridge = mock(AmqpChannelBridgeInterface::class, [
            'getAmqplibChannel' => $this->amqplibChannel,
            'unregisterChannel' => null,
        ]);
        $this->logger = mock(LoggerInterface::class, [
            'debug' => null,
        ]);
        $this->connectionBridge = mock(AmqpConnectionBridgeInterface::class, [
            'getAmqplibConnection' => $this->amqplibConnection,
            'createChannelBridge' => $this->channelBridge,
            'getLogger' => $this->logger,
        ]);

        AmqpBridge::bridgeConnection($this->amqpConnection, $this->connectionBridge);

        $this->amqpChannel = new AMQPChannel($this->amqpConnection);
    }

    public function testConstructorNotBeingCalledIsHandledCorrectly(): void
    {
        $extendedAmqpChannel = new class($this->amqpConnection) extends AMQPChannel {
            public function __construct(AMQPConnection $amqpConnection)
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
            ->createChannelBridge()
            ->once()
            ->andReturn($this->channelBridge);

        new AMQPChannel($this->amqpConnection);

        static::assertSame($this->channelBridge, AmqpBridge::getBridgeChannel($this->amqpChannel));
    }

    public function testDestructorClosesChannelWhenOpen(): void
    {
        $this->amqplibChannel->expects()
            ->close()
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

    public function testDestructorDoesNotCloseChannelWhenClosed(): void
    {
        $this->amqplibChannel->allows()
            ->is_open()
            ->andReturn(false);

        $this->amqplibChannel->expects()
            ->close()
            ->never();

        $this->amqpChannel = null; // Invoke the destructor synchronously (assuming no reference cycles).
    }

    /**
     * @dataProvider basicRecoverDataProvider
     */
    public function testBasicRecoverLogsAttemptAsDebug(bool $requeue): void
    {
        $this->amqplibChannel->allows()
            ->basic_recover($requeue);

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
    public function testBasicRecoverGoesViaAmqplib(bool $requeue): void
    {
        $this->amqplibChannel->expects()
            ->basic_recover($requeue)
            ->once();

        static::assertTrue($this->amqpChannel->basicRecover($requeue));
    }

    public function testBasicRecoverHandlesAmqplibExceptionCorrectly(): void
    {
        $exception = new AMQPProtocolChannelException(21, 'my text', [1, 2, 3]);

        $this->amqplibChannel->allows()
            ->basic_recover(true)
            ->andThrow($exception);

        $this->expectException(AMQPChannelException::class);
        $this->expectExceptionMessage('AMQPChannel::basicRecover(): Amqplib failure: my text');
        $this->logger->expects()
            ->logAmqplibException('AMQPChannel::basicRecover', $exception)
            ->once();

        $this->amqpChannel->basicRecover();
    }

    public static function basicRecoverDataProvider(): array
    {
        return [[true], [false]];
    }

    public function testCloseLogsAttemptAsDebug(): void
    {
        $this->amqplibChannel->allows()
            ->close();

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

    public function testCloseGoesViaAmqplib(): void
    {
        $this->amqplibChannel->expects()
            ->close()
            ->once();

        $this->amqpChannel->close();
    }

    public function testCloseHandlesAmqplibExceptionCorrectly(): void
    {
        $exception = new AMQPProtocolChannelException(21, 'my text', [1, 2, 3]);
        $this->amqplibChannel->allows()
            ->close()
            ->andThrow($exception);

        $this->expectException(AMQPChannelException::class);
        $this->expectExceptionMessage('AMQPChannel::close(): Amqplib failure: my text');
        $this->logger->expects()
            ->logAmqplibException('AMQPChannel::close', $exception)
            ->once();

        $this->amqpChannel->close();
    }

    public function testCloseHandlesConstructorNotBeingCalledCorrectly(): void
    {
        $extendedAmqpChannel = new class($this->amqpConnection) extends AMQPChannel {
            public function __construct(AMQPConnection $amqpConnection)
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
        $this->amqplibChannel->allows()
            ->is_open()
            ->andReturnFalse();

        $this->logger->expects()
            ->debug('AMQPChannel::close(): Channel already closed')
            ->once();
        // No attempt should be made to close the already-closed channel.
        $this->amqplibChannel->expects()
            ->close()
            ->never();

        $this->amqpChannel->close();
    }

    public function testCommitTransactionLogsAttemptAsDebug(): void
    {
        $this->amqplibChannel->allows()
            ->tx_commit();

        $this->logger->expects()
            ->debug('AMQPChannel::commitTransaction(): Transaction commit attempt')
            ->once();

        $this->amqpChannel->commitTransaction();
    }

    public function testCommitTransactionGoesViaAmqplib(): void
    {
        $this->amqplibChannel->expects()
            ->tx_commit()
            ->once();

        static::assertTrue($this->amqpChannel->commitTransaction());
    }

    public function testCommitTransactionHandlesAmqplibExceptionCorrectly(): void
    {
        $exception = new AMQPProtocolChannelException(21, 'my text', [1, 2, 3]);

        $this->amqplibChannel->allows()
            ->tx_commit()
            ->andThrow($exception);

        $this->expectException(AMQPChannelException::class);
        $this->expectExceptionMessage('AMQPChannel::commitTransaction(): Amqplib failure: my text');
        $this->logger->expects()
            ->logAmqplibException('AMQPChannel::commitTransaction', $exception)
            ->once();

        $this->amqpChannel->commitTransaction();
    }

    public function testRollbackTransactionLogsAttemptAsDebug(): void
    {
        $this->amqplibChannel->allows()
            ->tx_rollback();

        $this->logger->expects()
            ->debug('AMQPChannel::rollbackTransaction(): Transaction rollback attempt')
            ->once();

        $this->amqpChannel->rollbackTransaction();
    }

    public function testRollbackTransactionGoesViaAmqplib(): void
    {
        $this->amqplibChannel->expects()
            ->tx_rollback()
            ->once();

        static::assertTrue($this->amqpChannel->rollbackTransaction());
    }

    public function testRollbackTransactionHandlesAmqplibExceptionCorrectly(): void
    {
        $exception = new AMQPProtocolChannelException(21, 'my text', [1, 2, 3]);

        $this->amqplibChannel->allows()
            ->tx_rollback()
            ->andThrow($exception);

        $this->expectException(AMQPChannelException::class);
        $this->expectExceptionMessage('AMQPChannel::rollbackTransaction(): Amqplib failure: my text');
        $this->logger->expects()
            ->logAmqplibException('AMQPChannel::rollbackTransaction', $exception)
            ->once();

        $this->amqpChannel->rollbackTransaction();
    }

    public function testStartTransactionLogsAttemptAsDebug(): void
    {
        $this->amqplibChannel->allows()
            ->tx_select();

        $this->logger->expects()
            ->debug('AMQPChannel::startTransaction(): Transaction start attempt')
            ->once();

        $this->amqpChannel->startTransaction();
    }

    public function testStartTransactionGoesViaAmqplib(): void
    {
        $this->amqplibChannel->expects()
            ->tx_select()
            ->once();

        static::assertTrue($this->amqpChannel->startTransaction());
    }

    public function testStartTransactionHandlesAmqplibExceptionCorrectly(): void
    {
        $exception = new AMQPProtocolChannelException(21, 'my text', [1, 2, 3]);

        $this->amqplibChannel->allows()
            ->tx_select()
            ->andThrow($exception);

        $this->expectException(AMQPChannelException::class);
        $this->expectExceptionMessage('AMQPChannel::startTransaction(): Amqplib failure: my text');
        $this->logger->expects()
            ->logAmqplibException('AMQPChannel::startTransaction', $exception)
            ->once();

        $this->amqpChannel->startTransaction();
    }
}
