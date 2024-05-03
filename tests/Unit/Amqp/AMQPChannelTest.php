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
use Asmblah\PhpAmqpCompat\Connection\Config\ConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Exception\ExceptionHandlerInterface;
use Asmblah\PhpAmqpCompat\Logger\LoggerInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Exception;
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
    private MockInterface&AMQPConnection $amqpConnection;
    private MockInterface&AmqplibChannel $amqplibChannel;
    private MockInterface&AmqplibConnection $amqplibConnection;
    private MockInterface&AmqpChannelBridgeInterface $channelBridge;
    private MockInterface&ConnectionConfigInterface $connectionConfig;
    private MockInterface&AmqpConnectionBridgeInterface $connectionBridge;
    private MockInterface&ExceptionHandlerInterface $exceptionHandler;
    private MockInterface&LoggerInterface $logger;

    public function setUp(): void
    {
        $this->amqpConnection = mock(AMQPConnection::class, [
            'isConnected' => true,
        ]);
        $this->amqplibChannel = mock(AmqplibChannel::class, [
            'basic_qos' => null,
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
        $this->connectionConfig = mock(ConnectionConfigInterface::class, [
            'getGlobalPrefetchCount' => 100,
            'getGlobalPrefetchSize' => 512,
            'getPrefetchCount' => 50,
            'getPrefetchSize' => 128,
        ]);
        $this->exceptionHandler = mock(ExceptionHandlerInterface::class);
        $this->logger = mock(LoggerInterface::class, [
            'debug' => null,
        ]);
        $this->connectionBridge = mock(AmqpConnectionBridgeInterface::class, [
            'getAmqplibConnection' => $this->amqplibConnection,
            'createChannelBridge' => $this->channelBridge,
            'getConnectionConfig' => $this->connectionConfig,
            'getExceptionHandler' => $this->exceptionHandler,
            'getLogger' => $this->logger,
        ]);

        AmqpBridge::bridgeConnection($this->amqpConnection, $this->connectionBridge);

        $this->exceptionHandler->allows('handleException')
            ->andReturnUsing(function (Exception $libraryException, string $exceptionClass, string $methodName) {
                throw new Exception(sprintf(
                    'handleException() :: %s() :: Library Exception<%s> -> %s :: message(%s)',
                    $methodName,
                    $libraryException::class,
                    $exceptionClass,
                    $libraryException->getMessage()
                ));
            })
            ->byDefault();

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
            ->createChannelBridge()
            ->once()
            ->andReturn($this->channelBridge);

        new AMQPChannel($this->amqpConnection);

        static::assertSame($this->channelBridge, AmqpBridge::getBridgeChannel($this->amqpChannel));
    }

    public function testConstructorSetsPrefetchSettingsWhenGlobalAreNonZero(): void
    {
        $this->amqplibChannel->expects()
            ->basic_qos(128, 50, false)
            ->once()
            ->globally()->ordered();
        $this->amqplibChannel->expects()
            ->basic_qos(512, 100, true)
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

        $this->amqplibChannel->expects()
            ->basic_qos(128, 50, false)
            ->once()
            ->globally()->ordered();
        $this->amqplibChannel->expects('basic_qos')
            ->never()
            ->globally()->ordered(); // Global must be configured last.

        new AMQPChannel($this->amqpConnection);
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
        $this->amqplibChannel->allows()
            ->basic_recover(true)
            ->andThrow(new AMQPProtocolChannelException(21, 'my text', [1, 2, 3]));

        $this->expectExceptionMessage(
            'handleException() :: AMQPChannel::basicRecover() :: ' .
            'Library Exception<PhpAmqpLib\Exception\AMQPProtocolChannelException> -> AMQPChannelException :: ' .
            'message(my text)'
        );

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
        $this->amqplibChannel->allows()
            ->basic_recover(true);

        $this->logger->expects()
            ->debug('AMQPChannel::basicRecover(): Recovered')
            ->once();

        $this->amqpChannel->basicRecover();
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
        $this->amqplibChannel->allows()
            ->close()
            ->andReturnUsing(function () {
                $this->amqplibChannel->allows()
                    ->is_open()
                    ->andReturn(false);

                throw new AMQPProtocolChannelException(21, 'my text', [1, 2, 3]);
            });

        $this->expectExceptionMessage(
            'handleException() :: AMQPChannel::close() :: ' .
            'Library Exception<PhpAmqpLib\Exception\AMQPProtocolChannelException> -> AMQPChannelException :: ' .
            'message(my text)'
        );

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

    public function testCloseLogsSuccessAsDebug(): void
    {
        $this->amqplibChannel->allows()
            ->close();

        $this->logger->expects()
            ->debug('AMQPChannel::close(): Channel closed')
            ->once();

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
        $this->amqplibChannel->allows()
            ->tx_commit()
            ->andThrow(new AMQPProtocolChannelException(21, 'my text', [1, 2, 3]));

        $this->expectExceptionMessage(
            'handleException() :: AMQPChannel::commitTransaction() :: ' .
            'Library Exception<PhpAmqpLib\Exception\AMQPProtocolChannelException> -> AMQPChannelException :: ' .
            'message(my text)'
        );

        $this->amqpChannel->commitTransaction();
    }

    public function testCommitTransactionLogsSuccessAsDebug(): void
    {
        $this->amqplibChannel->allows()
            ->tx_commit();

        $this->logger->expects()
            ->debug('AMQPChannel::commitTransaction(): Transaction committed')
            ->once();

        $this->amqpChannel->commitTransaction();
    }

    /**
     * @dataProvider qosDataProvider
     */
    public function testQosLogsAttemptAsDebug(int $prefetchSize, int $prefetchCount, bool $global): void
    {
        $this->amqplibChannel->allows()
            ->basic_qos($prefetchSize, $prefetchCount, $global);

        $this->logger->expects()
            ->debug('AMQPChannel::qos(): QOS setting change attempt', [
                'count' => $prefetchCount,
                'global' => $global,
                'size' => $prefetchSize,
            ])
            ->once();

        // @phpstan-ignore-next-line
        $this->amqpChannel->qos($prefetchSize, $prefetchCount, $global);
    }

    /**
     * @dataProvider qosDataProvider
     */
    public function testQosGoesViaAmqplib(int $prefetchSize, int $prefetchCount, bool $global): void
    {
        $this->amqplibChannel->expects()
            ->basic_qos($prefetchSize, $prefetchCount, $global)
            ->once();

        // @phpstan-ignore-next-line
        static::assertTrue($this->amqpChannel->qos($prefetchSize, $prefetchCount, $global));
    }

    public function testQosDefaultsGlobalToFalse(): void
    {
        $this->amqplibChannel->expects()
            ->basic_qos(256, 21, false)
            ->once();

        static::assertTrue($this->amqpChannel->qos(256, 21));
    }

    /**
     * @dataProvider qosDataProvider
     */
    public function testQosHandlesAmqplibExceptionCorrectly(int $prefetchSize, int $prefetchCount, bool $global): void
    {
        $this->amqplibChannel->allows()
            ->basic_qos($prefetchSize, $prefetchCount, $global)
            ->andThrow(new AMQPProtocolChannelException(21, 'my text', [1, 2, 3]));

        $this->expectExceptionMessage(
            'handleException() :: AMQPChannel::qos() :: ' .
            'Library Exception<PhpAmqpLib\Exception\AMQPProtocolChannelException> -> AMQPChannelException :: ' .
            'message(my text)'
        );

        // @phpstan-ignore-next-line
        $this->amqpChannel->qos($prefetchSize, $prefetchCount, $global);
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

    public function testQosLogsSuccessAsDebug(): void
    {
        $this->amqplibChannel->allows()
            ->basic_qos(512, 20, false);

        $this->logger->expects()
            ->debug('AMQPChannel::qos(): QOS settings changed')
            ->once();

        $this->amqpChannel->qos(512, 20);
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
        $this->amqplibChannel->allows()
            ->tx_rollback()
            ->andThrow(new AMQPProtocolChannelException(21, 'my text', [1, 2, 3]));

        $this->expectExceptionMessage(
            'handleException() :: AMQPChannel::rollbackTransaction() :: ' .
            'Library Exception<PhpAmqpLib\Exception\AMQPProtocolChannelException> -> AMQPChannelException :: ' .
            'message(my text)'
        );

        $this->amqpChannel->rollbackTransaction();
    }

    public function testRollbackTransactionLogsSuccessAsDebug(): void
    {
        $this->amqplibChannel->allows()
            ->tx_rollback();

        $this->logger->expects()
            ->debug('AMQPChannel::rollbackTransaction(): Transaction rolled back')
            ->once();

        $this->amqpChannel->rollbackTransaction();
    }

    public function testSetGlobalPrefetchCountLogsAttemptAsDebug(): void
    {
        $this->amqplibChannel->allows()
            ->basic_qos(0, 100, true);

        $this->logger->expects()
            ->debug('AMQPChannel::setGlobalPrefetchCount(): Global prefetch count change attempt', [
                'count' => 100,
            ])
            ->once();

        $this->amqpChannel->setGlobalPrefetchCount(100);
    }

    public function testSetGlobalPrefetchCountGoesViaAmqplib(): void
    {
        $this->amqplibChannel->expects()
            ->basic_qos(0, 20, true)
            ->once();

        static::assertTrue($this->amqpChannel->setGlobalPrefetchCount(20));
    }

    public function testSetGlobalPrefetchCountHandlesAmqplibExceptionCorrectly(): void
    {
        $this->amqplibChannel->allows()
            ->basic_qos(0, 10, true)
            ->andThrow(new AMQPProtocolChannelException(21, 'my text', [1, 2, 3]));

        $this->expectExceptionMessage(
            'handleException() :: AMQPChannel::setGlobalPrefetchCount() :: ' .
            'Library Exception<PhpAmqpLib\Exception\AMQPProtocolChannelException> -> AMQPChannelException :: ' .
            'message(my text)'
        );

        $this->amqpChannel->setGlobalPrefetchCount(10);
    }

    public function testSetGlobalPrefetchCountLogsSuccessAsDebug(): void
    {
        $this->amqplibChannel->allows()
            ->basic_qos(0, 100, true);

        $this->logger->expects()
            ->debug('AMQPChannel::setGlobalPrefetchCount(): Global prefetch count changed')
            ->once();

        $this->amqpChannel->setGlobalPrefetchCount(100);
    }

    public function testSetGlobalPrefetchSizeLogsAttemptAsDebug(): void
    {
        $this->amqplibChannel->allows()
            ->basic_qos(128, 0, true);

        $this->logger->expects()
            ->debug('AMQPChannel::setGlobalPrefetchSize(): Global prefetch size change attempt', [
                'size' => 128,
            ])
            ->once();

        $this->amqpChannel->setGlobalPrefetchSize(128);
    }

    public function testSetGlobalPrefetchSizeGoesViaAmqplib(): void
    {
        $this->amqplibChannel->expects()
            ->basic_qos(128, 0, true)
            ->once();

        static::assertTrue($this->amqpChannel->setGlobalPrefetchSize(128));
    }

    public function testSetGlobalPrefetchSizeHandlesAmqplibExceptionCorrectly(): void
    {
        $this->amqplibChannel->allows()
            ->basic_qos(64, 0, true)
            ->andThrow(new AMQPProtocolChannelException(21, 'my text', [1, 2, 3]));

        $this->expectExceptionMessage(
            'handleException() :: AMQPChannel::setGlobalPrefetchSize() :: ' .
            'Library Exception<PhpAmqpLib\Exception\AMQPProtocolChannelException> -> AMQPChannelException :: ' .
            'message(my text)'
        );

        $this->amqpChannel->setGlobalPrefetchSize(64);
    }

    public function testSetGlobalPrefetchSizeLogsSuccessAsDebug(): void
    {
        $this->amqplibChannel->allows()
            ->basic_qos(512, 0, true);

        $this->logger->expects()
            ->debug('AMQPChannel::setGlobalPrefetchSize(): Global prefetch size changed')
            ->once();

        $this->amqpChannel->setGlobalPrefetchSize(512);
    }

    public function testSetPrefetchCountLogsAttemptAsDebug(): void
    {
        $this->amqplibChannel->allows()
            ->basic_qos(0, 7, false);
        $this->amqplibChannel->allows()
            ->basic_qos(512, 100, true);

        $this->logger->expects()
            ->debug('AMQPChannel::setPrefetchCount(): Non-global prefetch count change attempt', [
                'count' => 7,
            ])
            ->once();

        $this->amqpChannel->setPrefetchCount(7);
    }

    public function testSetPrefetchCountGoesViaAmqplib(): void
    {
        $this->amqplibChannel->expects()
            ->basic_qos(0, 8, false)
            ->once();
        // Global settings must be re-applied.
        $this->amqplibChannel->expects()
            ->basic_qos(512, 100, true)
            ->once();

        static::assertTrue($this->amqpChannel->setPrefetchCount(8));
    }

    public function testSetPrefetchCountHandlesAmqplibExceptionCorrectly(): void
    {
        $this->amqplibChannel->allows()
            ->basic_qos(0, 6, false)
            ->andThrow(new AMQPProtocolChannelException(21, 'my text', [1, 2, 3]));
        $this->amqplibChannel->allows()
            ->basic_qos(512, 100, true);

        $this->expectExceptionMessage(
            'handleException() :: AMQPChannel::setPrefetchCount() :: ' .
            'Library Exception<PhpAmqpLib\Exception\AMQPProtocolChannelException> -> AMQPChannelException :: ' .
            'message(my text)'
        );

        $this->amqpChannel->setPrefetchCount(6);
    }

    public function testSetPrefetchCountLogsSuccessAsDebug(): void
    {
        $this->amqplibChannel->allows()
            ->basic_qos(0, 7, false);
        $this->amqplibChannel->allows()
            ->basic_qos(512, 100, true);

        $this->logger->expects()
            ->debug('AMQPChannel::setPrefetchCount(): Non-global prefetch count changed')
            ->once();

        $this->amqpChannel->setPrefetchCount(7);
    }

    public function testSetPrefetchSizeLogsAttemptAsDebug(): void
    {
        $this->amqplibChannel->allows()
            ->basic_qos(128, 0, false);
        $this->amqplibChannel->allows()
            ->basic_qos(512, 100, true);

        $this->logger->expects()
            ->debug('AMQPChannel::setPrefetchSize(): Non-global prefetch size change attempt', [
                'size' => 128,
            ])
            ->once();

        $this->amqpChannel->setPrefetchSize(128);
    }

    public function testSetPrefetchSizeGoesViaAmqplib(): void
    {
        $this->amqplibChannel->allows()
            ->basic_qos(128, 0, false)
            ->once();
        // Global settings must be re-applied.
        $this->amqplibChannel->expects()
            ->basic_qos(512, 100, true)
            ->once();

        static::assertTrue($this->amqpChannel->setPrefetchSize(128));
    }

    public function testSetPrefetchSizeHandlesAmqplibExceptionCorrectly(): void
    {
        $this->amqplibChannel->allows()
            ->basic_qos(64, 0, false)
            ->andThrow(new AMQPProtocolChannelException(21, 'my text', [1, 2, 3]));
        $this->amqplibChannel->allows()
            ->basic_qos(512, 100, true);

        $this->expectExceptionMessage(
            'handleException() :: AMQPChannel::setPrefetchSize() :: ' .
            'Library Exception<PhpAmqpLib\Exception\AMQPProtocolChannelException> -> AMQPChannelException :: ' .
            'message(my text)'
        );

        $this->amqpChannel->setPrefetchSize(64);
    }

    public function testSetPrefetchSizeLogsSuccessAsDebug(): void
    {
        $this->amqplibChannel->allows()
            ->basic_qos(64, 0, false);
        $this->amqplibChannel->allows()
            ->basic_qos(512, 100, true);

        $this->logger->expects()
            ->debug('AMQPChannel::setPrefetchSize(): Non-global prefetch size changed')
            ->once();

        $this->amqpChannel->setPrefetchSize(64);
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
        $this->amqplibChannel->allows()
            ->tx_select()
            ->andThrow(new AMQPProtocolChannelException(21, 'my text', [1, 2, 3]));

        $this->expectExceptionMessage(
            'handleException() :: AMQPChannel::startTransaction() :: ' .
            'Library Exception<PhpAmqpLib\Exception\AMQPProtocolChannelException> -> AMQPChannelException :: ' .
            'message(my text)'
        );

        $this->amqpChannel->startTransaction();
    }

    public function testStartTransactionLogsSuccessAsDebug(): void
    {
        $this->amqplibChannel->allows()
            ->tx_select();

        $this->logger->expects()
            ->debug('AMQPChannel::startTransaction(): Transaction started')
            ->once();

        $this->amqpChannel->startTransaction();
    }
}
