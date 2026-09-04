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
use AMQPEnvelope;
use AMQPExchange;
use AMQPQueue;
use AMQPQueueException;
use Asmblah\PhpAmqpCompat\AmqpManager;
use Asmblah\PhpAmqpCompat\Configuration\Configuration;
use Asmblah\PhpAmqpCompat\Connection\Config\DefaultConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Tests\Functional\AbstractFunctionalTestCase;
use Psr\Log\NullLogger;

/**
 * Class AMQPChannelTest.
 *
 * Tests AMQPChannel against a real AMQP broker server.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AMQPChannelTest extends AbstractFunctionalTestCase
{
    private AMQPChannel $amqpChannel;
    private AMQPConnection $amqpConnection;
    private AMQPExchange $amqpExchange;
    private AMQPQueue $amqpQueue;
    private float $uniqueTestIdentifier;

    public function setUp(): void
    {
        parent::setUp();

        $this->resetAmqpManager();
        AmqpManager::setConfiguration(new Configuration(new NullLogger()));

        $this->uniqueTestIdentifier = microtime(true);
        $this->connect();
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->amqpQueue->delete();
        $this->amqpConnection->disconnect();

        $this->resetAmqpManager();
    }

    public function testDestructorHandlesUnderlyingConnectionHavingBeenClosedButNotChannel(): void
    {
        /** @noinspection PhpUnusedLocalVariableInspection */
        $secondAmqpChannel = new AMQPChannel($this->amqpConnection);
        $this->amqpConnection->disconnect();

        $this->expectNotToPerformAssertions();

        $secondAmqpChannel = null;

        $this->connect();
    }

    public function testBasicRecoverRequeuesUnacknowledgedMessages(): void
    {
        $this->amqpExchange->publish('message to recover');

        // Fetch without auto-ack so the message remains unacknowledged on the channel.
        $this->amqpQueue->get();

        // Recover (requeue) unacknowledged messages.
        static::assertTrue($this->amqpChannel->basicRecover(true));

        // Message should be redelivered to the queue.
        $envelope = $this->amqpQueue->get();
        static::assertInstanceOf(AMQPEnvelope::class, $envelope);
        static::assertSame('message to recover', $envelope->getBody());
    }

    public function testCloseClosesChannel(): void
    {
        $this->amqpChannel->close();

        static::assertFalse($this->amqpChannel->isConnected());

        $this->reconnect(); // Re-establish for ->tearDown().
    }

    public function testCommitTransactionAppliesPublishedMessages(): void
    {
        $this->amqpChannel->startTransaction();
        $this->amqpExchange->publish('transactional message');
        static::assertTrue($this->amqpChannel->commitTransaction());

        // After commit, the message should be available.
        $envelope = $this->amqpQueue->get();
        static::assertInstanceOf(AMQPEnvelope::class, $envelope);
        static::assertSame('transactional message', $envelope->getBody());
    }

    public function testGetChannelIdIsPositiveInteger(): void
    {
        static::assertGreaterThan(0, $this->amqpChannel->getChannelId());
    }

    public function testGetConnectionReturnsConnection(): void
    {
        static::assertSame($this->amqpConnection, $this->amqpChannel->getConnection());
    }

    public function testGetConsumersIncludesActiveConsumer(): void
    {
        // Subscribe without starting the consume loop (null callback).
        $this->amqpQueue->consume(null, AMQP_NOPARAM, 'test-consumer');

        $consumers = $this->amqpChannel->getConsumers();
        static::assertCount(1, $consumers);
        static::assertArrayHasKey('test-consumer', $consumers);
        static::assertSame($this->amqpQueue, $consumers['test-consumer']);
    }

    public function testGetConsumersIsEmptyInitially(): void
    {
        static::assertSame([], $this->amqpChannel->getConsumers());
    }

    public function testGetGlobalPrefetchCountDefaultsToDefaultGlobalPrefetchCount(): void
    {
        static::assertSame(
            DefaultConnectionConfigInterface::DEFAULT_GLOBAL_PREFETCH_COUNT,
            $this->amqpChannel->getGlobalPrefetchCount()
        );
    }

    public function testGetGlobalPrefetchSizeDefaultsToDefaultGlobalPrefetchSize(): void
    {
        static::assertSame(
            DefaultConnectionConfigInterface::DEFAULT_GLOBAL_PREFETCH_SIZE,
            $this->amqpChannel->getGlobalPrefetchSize()
        );
    }

    public function testGetPrefetchCountDefaultsToDefaultPrefetchCount(): void
    {
        static::assertSame(
            DefaultConnectionConfigInterface::DEFAULT_PREFETCH_COUNT,
            $this->amqpChannel->getPrefetchCount()
        );
    }

    public function testGetPrefetchSizeDefaultsToDefaultPrefetchSize(): void
    {
        static::assertSame(
            DefaultConnectionConfigInterface::DEFAULT_PREFETCH_SIZE,
            $this->amqpChannel->getPrefetchSize()
        );
    }

    public function testIsConnectedReturnsFalseAfterConnectionDisconnects(): void
    {
        $this->amqpConnection->disconnect();

        static::assertFalse($this->amqpChannel->isConnected());

        $this->reconnect(); // Re-establish for ->tearDown().
    }

    public function testIsConnectedReturnsTrueWhenConnected(): void
    {
        static::assertTrue($this->amqpChannel->isConnected());
    }

    public function testQosSetsChannelPrefetchSettings(): void
    {
        // RabbitMQ does not support prefetch_size != 0, so size must be 0 (unlimited).
        static::assertTrue($this->amqpChannel->qos(0, 5));
    }

    public function testRollbackTransactionDiscardsPublishedMessages(): void
    {
        $this->amqpChannel->startTransaction();
        $this->amqpExchange->publish('transactional message');
        static::assertTrue($this->amqpChannel->rollbackTransaction());

        // After rollback, the message should NOT be available.
        static::assertFalse($this->amqpQueue->get());
    }

    public function testSetGlobalPrefetchCountChangesGlobalPrefetchCountAndClearsGlobalPrefetchSize(): void
    {
        $this->amqpChannel->setGlobalPrefetchCount(10);

        static::assertSame(10, $this->amqpChannel->getGlobalPrefetchCount());
        static::assertSame(0, $this->amqpChannel->getGlobalPrefetchSize()); // Size is implicitly cleared.
    }

    public function testSetGlobalPrefetchSizeChangesGlobalPrefetchSizeAndClearsGlobalPrefetchCount(): void
    {
        $this->amqpChannel->setGlobalPrefetchCount(10); // Set a non-zero count first.
        // RabbitMQ does not support prefetch_size != 0, so size must be 0 (unlimited).
        $this->amqpChannel->setGlobalPrefetchSize(0);

        static::assertSame(0, $this->amqpChannel->getGlobalPrefetchSize());
        static::assertSame(0, $this->amqpChannel->getGlobalPrefetchCount()); // Count is implicitly cleared.
    }

    public function testSetPrefetchCountChangesPrefetchCountAndClearsPrefetchSize(): void
    {
        $this->amqpChannel->setPrefetchCount(10);

        static::assertSame(10, $this->amqpChannel->getPrefetchCount());
        static::assertSame(0, $this->amqpChannel->getPrefetchSize()); // Size is implicitly cleared.
    }

    public function testSetPrefetchCountWithholdsFurtherMessagesWhileOneIsUnacknowledged(): void
    {
        $this->amqpConnection->setReadTimeout(0.5);
        $this->amqpChannel->setPrefetchCount(1);

        $this->amqpExchange->publish('message one');
        $this->amqpExchange->publish('message two');

        $receivedMessages = [];
        $caughtException = null;

        try {
            $this->amqpQueue->consume(
                function (AMQPEnvelope $envelope) use (&$receivedMessages) {
                    $receivedMessages[] = $envelope->getBody();
                    // Deliberately do _not_ ack - broker must not deliver the next message.
                }
            );
        } catch (AMQPQueueException $exception) {
            // Exception should be thrown when consume times out because broker won't deliver the second message
            // while the first message is unacknowledged (prefetch count = 1).
            $caughtException = $exception;
        }

        static::assertCount(1, $receivedMessages);
        static::assertSame('message one', $receivedMessages[0]);
        static::assertInstanceOf(AMQPQueueException::class, $caughtException);
        static::assertSame('Consumer timeout exceed', $caughtException->getMessage());
    }

    public function testSetPrefetchSizeChangesPrefetchSizeAndClearsPrefetchCount(): void
    {
        $this->amqpChannel->setPrefetchCount(5); // Set a non-zero count first.
        // RabbitMQ does not support prefetch_size != 0, so size must be 0 (unlimited).
        $this->amqpChannel->setPrefetchSize(0);

        static::assertSame(0, $this->amqpChannel->getPrefetchSize());
        static::assertSame(0, $this->amqpChannel->getPrefetchCount()); // Count is implicitly cleared.
    }

    public function testStartTransactionKeepsPublishedMessagesInvisibleWhileTransactionIsOpen(): void
    {
        static::assertTrue($this->amqpChannel->startTransaction());

        $this->amqpExchange->publish('transactional message');

        // While transaction is still open, the message should NOT be available.
        static::assertFalse($this->amqpQueue->get());
    }

    private function connect(): void
    {
        $this->amqpConnection = new AMQPConnection();
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

    private function reconnect(): void
    {
        $this->amqpConnection->disconnect();
        $this->connect();
    }
}
