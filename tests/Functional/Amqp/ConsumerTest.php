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
use AMQPExchange;
use AMQPQueue;
use AMQPQueueException;
use Asmblah\PhpAmqpCompat\AmqpManager;
use Asmblah\PhpAmqpCompat\Configuration\Configuration;
use Asmblah\PhpAmqpCompat\Tests\Functional\AbstractFunctionalTestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class ConsumerTest.
 *
 * Checks consumption against a real AMQP broker server.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ConsumerTest extends AbstractFunctionalTestCase
{
    private AMQPChannel $amqpChannel;
    private AMQPConnection $amqpConnection;
    private AMQPExchange $amqpExchange;
    private AMQPQueue $amqpQueue;
    private LoggerInterface $logger;

    public function setUp(): void
    {
        parent::setUp();

        $this->logger = new NullLogger();

        $this->resetAmqpManager();
        AmqpManager::setConfiguration(new Configuration($this->logger));

        $this->amqpConnection = new AMQPConnection();
        $this->amqpConnection->connect();
        $this->amqpChannel = new AMQPChannel($this->amqpConnection);

        $this->amqpExchange = new AMQPExchange($this->amqpChannel);
        $this->amqpExchange->setName('exchange-' . microtime(true));
        $this->amqpExchange->setType(AMQP_EX_TYPE_FANOUT);
        $this->amqpExchange->declareExchange();

        $this->amqpQueue = new AMQPQueue($this->amqpChannel);
        $this->amqpQueue->setName('queue-' . microtime(true));
        $this->amqpQueue->setFlags(AMQP_NOPARAM);
        $this->amqpQueue->declareQueue();
        $this->amqpQueue->bind($this->amqpExchange->getName());
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->amqpQueue->delete();
        $this->amqpConnection->disconnect();

        $this->resetAmqpManager();
    }

    public function testMessageCanBePublishedAndConsumed(): void
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

    public function testConsumptionStopsWhenNoMessageIsReceivedBeforeReadTimeout(): void
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
}
