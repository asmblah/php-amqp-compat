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
use Asmblah\PhpAmqpCompat\Tests\Functional\AbstractFunctionalTestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class AMQPQueueTest.
 *
 * Tests AMQPQueue against a real AMQP broker server.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AMQPQueueTest extends AbstractFunctionalTestCase
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
        $this->connect();
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->amqpQueue->delete();
        $this->amqpConnection->disconnect();

        $this->resetAmqpManager();
    }

    public function testAckAcknowledgesMessage(): void
    {
        $this->amqpExchange->publish('my message body');

        $envelope = $this->amqpQueue->get();

        static::assertInstanceOf(AMQPEnvelope::class, $envelope);
        static::assertTrue($this->amqpQueue->ack($envelope->getDeliveryTag()));
        $this->reconnect();
        static::assertFalse($this->amqpQueue->get());
    }

    public function testAckWithMultipleFlagAcknowledgesAllPreviousMessages(): void
    {
        $this->amqpExchange->publish('message one');
        $this->amqpExchange->publish('message two');

        $envelope1 = $this->amqpQueue->get();
        $envelope2 = $this->amqpQueue->get();
        static::assertInstanceOf(AMQPEnvelope::class, $envelope1);
        static::assertInstanceOf(AMQPEnvelope::class, $envelope2);

        // Acknowledge the last message with AMQP_MULTIPLE to acknowledge all previous ones too.
        static::assertTrue($this->amqpQueue->ack($envelope2->getDeliveryTag(), AMQP_MULTIPLE));
        $this->reconnect();
        static::assertFalse($this->amqpQueue->get());
    }

    public function testCancelCancelsConsumer(): void
    {
        // Subscribe without starting the consume loop (null callback).
        $this->amqpQueue->consume(null, AMQP_NOPARAM, 'test-consumer-tag');

        static::assertSame('test-consumer-tag', $this->amqpQueue->getConsumerTag());
        static::assertTrue($this->amqpQueue->cancel('test-consumer-tag'));
    }

    public function testConsumeCanConsumeAPublishedMessage(): void
    {
        /** @var AMQPEnvelope|null $consumedEnvelope */
        $consumedEnvelope = null;
        /** @var AMQPQueue|null $consumedEnvelopeQueue */
        $consumedEnvelopeQueue = null;

        // First publish...
        $this->amqpExchange->publish('my message body');

        // Then immediately consume.
        $this->amqpQueue->consume(
            function (AMQPEnvelope $envelope, AMQPQueue $queue) use (&$consumedEnvelope, &$consumedEnvelopeQueue) {
                $consumedEnvelope = $envelope;
                $consumedEnvelopeQueue = $queue;

                return false; // Stop consumer.
            }
        );

        static::assertInstanceOf(AMQPEnvelope::class, $consumedEnvelope);
        static::assertSame('my message body', $consumedEnvelope->getBody());
        static::assertSame($this->amqpQueue, $consumedEnvelopeQueue);
    }

    public function testConsumeConsumptionStopsWhenNoMessageIsReceivedBeforeReadTimeout(): void
    {
        // Note the `->consume()` call below is expected to hang for ~2 seconds.
        $this->amqpConnection->setReadTimeout(2);

        $this->expectException(AMQPQueueException::class);
        // This message matches the reference implementation.
        $this->expectExceptionMessageMatches('/^Consumer timeout exceed$/');

        $this->amqpQueue->consume(
            function () {
                $this->fail('Consumer should not be invoked');
            }
        );
    }

    public function testConsumeWithAutoAckFlagAutoAcknowledgesMessages(): void
    {
        $this->amqpExchange->publish('my message body');

        $consumedBody = null;
        $this->amqpQueue->consume(
            function (AMQPEnvelope $envelope) use (&$consumedBody) {
                $consumedBody = $envelope->getBody();

                return false; // Stop consuming.
            },
            AMQP_AUTOACK
        );

        static::assertSame('my message body', $consumedBody);
        // Message was auto-acknowledged; queue should be empty after reconnect.
        $this->reconnect();
        static::assertFalse($this->amqpQueue->get());
    }

    public function testDeclareQueueReturnsCurrentMessageCount(): void
    {
        $this->amqpExchange->publish('a message');

        // Re-declaring the same queue returns its current message count.
        $messageCount = $this->amqpQueue->declareQueue();

        static::assertSame(1, $messageCount);
    }

    public function testDeleteReturnsDeletedMessageCount(): void
    {
        // Use a dedicated queue so as not to affect the one cleaned up by ->tearDown().
        $queueToDelete = new AMQPQueue($this->amqpChannel);
        $queueToDelete->setName('queue-to-delete-' . $this->uniqueTestIdentifier);
        $queueToDelete->setFlags(AMQP_NOPARAM);
        $queueToDelete->declareQueue();
        $queueToDelete->bind($this->amqpExchange->getName());

        $this->amqpExchange->publish('message in queue');

        // Re-declare passively to confirm the message has arrived.
        static::assertSame(1, $queueToDelete->declareQueue());

        $deletedCount = $queueToDelete->delete();

        static::assertSame(1, $deletedCount);
    }

    public function testGetWithAutoAckFlagDoesNotRequireManualAcknowledgement(): void
    {
        $this->amqpExchange->publish('my message body');

        $envelope = $this->amqpQueue->get(AMQP_AUTOACK);

        static::assertInstanceOf(AMQPEnvelope::class, $envelope);
        static::assertSame('my message body', $envelope->getBody());
        // Message was auto-acknowledged; queue should be empty after reconnect.
        $this->reconnect();
        static::assertFalse($this->amqpQueue->get());
    }

    public function testGetArgumentReturnsFalseForMissingArgument(): void
    {
        static::assertFalse($this->amqpQueue->getArgument('non-existent'));
    }

    public function testGetArgumentReturnsArgumentValue(): void
    {
        $this->amqpQueue->setArgument('x-message-ttl', 60000);

        static::assertSame(60000, $this->amqpQueue->getArgument('x-message-ttl'));
    }

    public function testGetArgumentsReturnsAllArguments(): void
    {
        $this->amqpQueue->setArguments(['x-message-ttl' => 60000, 'x-max-length' => 100]);
        $this->amqpQueue->setArgument('x-extra', 1234);

        static::assertEquals(
            ['x-message-ttl' => 60000, 'x-max-length' => 100, 'x-extra' => 1234],
            $this->amqpQueue->getArguments()
        );
    }

    public function testGetChannelReturnsChannelPassedToConstructor(): void
    {
        static::assertSame($this->amqpChannel, $this->amqpQueue->getChannel());
    }

    public function testGetConnectionReturnsConnection(): void
    {
        static::assertSame($this->amqpConnection, $this->amqpQueue->getConnection());
    }

    public function testGetConsumerTagIsNullBeforeAnyConsume(): void
    {
        static::assertNull($this->amqpQueue->getConsumerTag());
    }

    public function testGetConsumerTagIsSetBeforeConsumptionStarts(): void
    {
        $this->amqpExchange->publish('my message body');

        $this->amqpQueue->consume(
            function () {
                static::assertSame('my-test-consumer-tag', $this->amqpQueue->getConsumerTag());

                return false; // Stop immediately.
            },
            AMQP_NOPARAM,
            'my-test-consumer-tag'
        );
    }

    public function testGetConsumerTagIsSetAfterConsume(): void
    {
        $this->amqpExchange->publish('my message body');

        $this->amqpQueue->consume(
            function () {
                return false; // Stop immediately.
            },
            AMQP_NOPARAM,
            'my-test-consumer-tag'
        );

        static::assertSame('my-test-consumer-tag', $this->amqpQueue->getConsumerTag());
    }

    public function testGetFlagsReflectsSetFlags(): void
    {
        $this->amqpQueue->setFlags(AMQP_DURABLE | AMQP_PASSIVE);

        static::assertSame(AMQP_DURABLE | AMQP_PASSIVE, $this->amqpQueue->getFlags());
    }

    public function testGetNameReturnsQueueName(): void
    {
        static::assertSame('queue-' . $this->uniqueTestIdentifier, $this->amqpQueue->getName());
    }

    public function testHasArgumentReturnsTrueForExistingArgument(): void
    {
        $this->amqpQueue->setArgument('x-message-ttl', 60000);

        static::assertTrue($this->amqpQueue->hasArgument('x-message-ttl'));
        static::assertFalse($this->amqpQueue->hasArgument('x-max-length'));
    }

    public function testNackWithoutRequeuePurgesMessage(): void
    {
        $this->amqpExchange->publish('my message body');

        $envelope = $this->amqpQueue->get();
        static::assertInstanceOf(AMQPEnvelope::class, $envelope);

        // Note that `basic.nack` is asynchronous and so in theory this test may have a race condition.
        static::assertTrue($this->amqpQueue->nack($envelope->getDeliveryTag()));

        $this->reconnect();
        static::assertFalse($this->amqpQueue->get());
    }

    public function testNackWithRequeuePutsMessageBackOnQueue(): void
    {
        $this->amqpExchange->publish('my message body');
        $envelope = $this->amqpQueue->get();

        static::assertInstanceOf(AMQPEnvelope::class, $envelope);
        static::assertTrue($this->amqpQueue->nack($envelope->getDeliveryTag(), AMQP_REQUEUE));

        $this->reconnect();
        $redelivered = $this->amqpQueue->get();
        static::assertInstanceOf(AMQPEnvelope::class, $redelivered);
        static::assertSame('my message body', $redelivered->getBody());
        $this->amqpQueue->ack($redelivered->getDeliveryTag());
    }

    public function testPurgeEmptiesQueue(): void
    {
        $this->amqpExchange->publish('message one');
        $this->amqpExchange->publish('message two');

        static::assertTrue($this->amqpQueue->purge());
        static::assertFalse($this->amqpQueue->get());
    }

    public function testRejectNegativelyAcknowledgesTheMessage(): void
    {
        // First publish...
        $this->amqpExchange->publish('my message body');

        // Then immediately fetch.
        $fetchedEnvelope = $this->amqpQueue->get();

        static::assertInstanceOf(AMQPEnvelope::class, $fetchedEnvelope);
        // Note that `basic.reject` is asynchronous and so in theory this test may have a race condition.
        static::assertTrue($this->amqpQueue->reject($fetchedEnvelope->getDeliveryTag()));
        $this->reconnect();
        static::assertFalse($this->amqpQueue->get());
    }

    public function testSetArgumentRemovesArgumentWhenNullGiven(): void
    {
        $this->amqpQueue->setArgument('x-message-ttl', 60000);
        $this->amqpQueue->setArgument('x-message-ttl', null);

        static::assertFalse($this->amqpQueue->hasArgument('x-message-ttl'));
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
