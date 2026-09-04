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

namespace Asmblah\PhpAmqpCompat\Tests\Functional\Amqp;

use AMQPChannel;
use AMQPConnection;
use AMQPEnvelope;
use AMQPException;
use AMQPExchange;
use AMQPQueue;
use Asmblah\PhpAmqpCompat\AmqpManager;
use Asmblah\PhpAmqpCompat\Bridge\AmqpBridge;
use Asmblah\PhpAmqpCompat\Configuration\Configuration;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transport\Transport;
use Asmblah\PhpAmqpCompat\Tests\Functional\AbstractFunctionalTestCase;
use Closure;
use PhpAmqpLib\Wire\IO\AbstractIO;
use PhpAmqpLib\Wire\IO\StreamIO;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class HeartbeatTest.
 *
 * Checks heartbeat handling against a real AMQP broker server.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class HeartbeatTest extends AbstractFunctionalTestCase
{
    private AMQPChannel $amqpChannel;
    private AMQPConnection $amqpConnection;
    private AMQPExchange $amqpExchange;
    private AMQPQueue $amqpQueue;
    private LoggerInterface $logger;
    private float $uniqueTestIdentifier;

    public function setUp(): void
    {
        parent::setUp();

        $this->logger = new NullLogger();

        $this->resetAmqpManager();
        AmqpManager::setConfiguration(new Configuration($this->logger));

        $this->uniqueTestIdentifier = microtime(true);
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->connect(); // Reconnect, as the connection should be broken due to the missed heartbeats.
        $this->amqpQueue->delete();
        $this->amqpConnection->disconnect();

        $this->resetAmqpManager();
    }

    public function testClientNotSendingHeartbeatInTimeIsHandledCorrectly(): void
    {
        $this->connect([
            'heartbeat' => 2,
        ]);
        // First publish...
        $this->amqpExchange->publish('my message body');

        // Then immediately fetch.
        $fetchedEnvelope = $this->amqpQueue->get();

        static::assertInstanceOf(AMQPEnvelope::class, $fetchedEnvelope);

        $this->expectException(AMQPException::class);
        $this->expectExceptionMessage('Heartbeat missed: Missed server heartbeat');

        // Miss the window in which the client heartbeat needs to be sent (heartbeat * 2 + padding of one second).
        sleep(5);

        // Attempt an ack, which should fail with the relevant missed heartbeat exception.
        $this->amqpQueue->ack($fetchedEnvelope->getDeliveryTag());
    }

    public function testServerHeartbeatNotBeingReceivedInTimeIsHandledCorrectly(): void
    {
        $this->connect([
            'heartbeat' => 10, // Allow plenty of time - we don't want the server to actually drop the connection.
        ]);
        // First publish...
        $this->amqpExchange->publish('my message body');

        // Then immediately fetch.
        $fetchedEnvelope = $this->amqpQueue->get();

        static::assertInstanceOf(AMQPEnvelope::class, $fetchedEnvelope);

        $this->expectException(AMQPException::class);
        $this->expectExceptionMessage('Library error: Server connection error: 0, message: Missed server heartbeat');

        // Fake a lower heartbeat interval...
        /** @var Transport $transport */
        $transport = AmqpBridge::getBridgeConnection($this->amqpConnection)->getTransport();
        $amqplibConnection = $transport->getAmqplibConnection();
        /** @var StreamIO $streamIo */
        $streamIo = $amqplibConnection->getIO();
        static::assertInstanceOf(StreamIO::class, $streamIo);
        Closure::bind(function () {
            $this->heartbeat = 1;
        }, $streamIo, AbstractIO::class)();

        // ... and then miss the window in which the server heartbeat should have been received
        //     (heartbeat * 2 + padding of one second).
        sleep(3);

        // Attempt an ack, which should fail with the relevant missed heartbeat exception.
        $this->amqpQueue->ack($fetchedEnvelope->getDeliveryTag());
    }

    /**
     * @param array<mixed> $credentials
     */
    private function connect(array $credentials = []): void
    {
        $this->amqpConnection = new AMQPConnection($credentials);
        $this->amqpConnection->connect();
        $this->amqpChannel = new AMQPChannel($this->amqpConnection);

        $this->amqpExchange = new AMQPExchange($this->amqpChannel);
        $this->amqpExchange->setName('exchange-' . $this->uniqueTestIdentifier);
        $this->amqpExchange->setType(AMQP_EX_TYPE_FANOUT);
        $this->amqpExchange->declareExchange();

        $this->amqpQueue = new AMQPQueue($this->amqpChannel);
        $this->amqpQueue->setName('queue-' . $this->uniqueTestIdentifier);
        $this->amqpQueue->setFlags(AMQP_NOPARAM);
        $this->amqpQueue->declareQueue();
        $this->amqpQueue->bind($this->amqpExchange->getName());
    }
}
