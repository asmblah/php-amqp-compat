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

namespace Asmblah\PhpAmqpCompat\Tests\Functional\Amqp\Amqp;

use AMQPChannel;
use AMQPConnection;
use AMQPQueue;
use AMQPQueueException;
use Asmblah\PhpAmqpCompat\AmqpManager;
use Asmblah\PhpAmqpCompat\Bridge\AmqpBridge;
use Asmblah\PhpAmqpCompat\Configuration\Configuration;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transport\Transport;
use Asmblah\PhpAmqpCompat\Tests\Functional\AbstractFunctionalTestCase;
use Mockery\MockInterface;
use PhpAmqpLib\Wire\IO\StreamIO;
use Psr\Log\LoggerInterface;

/**
 * Class AMQPConnectionTest.
 *
 * Tests AMQPConnection against a real AMQP broker server.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AMQPConnectionTest extends AbstractFunctionalTestCase
{
    private MockInterface&LoggerInterface $logger;

    public function setUp(): void
    {
        parent::setUp();

        $this->logger = mock(LoggerInterface::class, [
            'critical' => null,
            'log' => null,
        ]);

        $this->resetAmqpManager();
        AmqpManager::setConfiguration(new Configuration($this->logger));
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->resetAmqpManager();
    }

    public function testSetReadTimeoutChangesRealAmqpConnectionTimeoutOnline(): void
    {
        $amqpConnection = new AMQPConnection(['read_timeout' => 10]);
        $amqpConnection->connect();

        $amqpConnection->setReadTimeout(18.5);

        static::assertTrue($amqpConnection->isConnected());
        static::assertSame(18.5, $amqpConnection->getReadTimeout());
        /** @var Transport $transport */
        $transport = AmqpBridge::getBridgeConnection($amqpConnection)->getTransport();
        $amqplibConnection = $transport->getAmqplibConnection();
        static::assertSame(
            18.5,
            $amqplibConnection->getReadTimeout()
        );
        /** @var StreamIO $streamIo */
        $streamIo = $amqplibConnection->getIO();
        static::assertInstanceOf(StreamIO::class, $streamIo);
        $socket = socket_import_stream($streamIo->getSocket());
        static::assertEquals(
            [
                'sec' => 18,
                'usec' => 500000, // 0.5s in microseconds above the 18s.
            ],
            socket_get_option($socket, SOL_SOCKET, SO_RCVTIMEO),
            'Underlying socket SO_RCVTIMEO option should be changed to 18.5s'
        );
    }

    public function testSetReadTimeoutCausesActualTimeoutsDuringReadOperations(): void
    {
        $timeout = 2.5; // 2.5 seconds timeout.
        $amqpConnection = new AMQPConnection();
        $amqpConnection->connect();
        $amqpChannel = new AMQPChannel($amqpConnection);
        $amqpQueue = new AMQPQueue($amqpChannel);
        $amqpQueue->setName('test_timeout_queue_' . uniqid('', true));
        $amqpQueue->setFlags(AMQP_EXCLUSIVE | AMQP_AUTODELETE);
        $amqpQueue->declareQueue();
        $start = microtime(true);

        $this->expectException(AMQPQueueException::class);
        $this->expectExceptionMessage('Consumer timeout exceed');

        $amqpConnection->setReadTimeout($timeout);

        try {
            // Try to consume from the empty queue - this should time out
            // after read-timeout exceeded, as no messages will be received.
            $amqpQueue->consume(function() {
                // This callback should never be called since queue is empty.
                $this->fail('Callback should not be called for empty queue');
            }, AMQP_NOPARAM, 'test_consumer_' . uniqid('', true));
        } catch (AMQPQueueException $exception) {
            $end = microtime(true);
            $actualDuration = $end - $start;

            // Ensure we're reasonably close to the expected timeout.
            static::assertGreaterThan(
                $timeout * 0.8, // At least 80% of expected timeout.
                $actualDuration,
                'Timeout occurred too early'
            );

            static::assertLessThan(
                $timeout * 1.5, // No more than 150% of expected timeout.
                $actualDuration,
                'Timeout took too long'
            );

            throw $exception;
        }
    }
}
