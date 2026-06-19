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
use Asmblah\PhpAmqpCompat\AmqpManager;
use Asmblah\PhpAmqpCompat\Configuration\Configuration;
use Asmblah\PhpAmqpCompat\Tests\Functional\AbstractFunctionalTestCase;
use Psr\Log\NullLogger;

/**
 * Class AMQPExchangeTest.
 *
 * Tests AMQPExchange against a real AMQP broker server.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AMQPExchangeTest extends AbstractFunctionalTestCase
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
        $this->amqpExchange->delete();
        $this->amqpConnection->disconnect();

        $this->resetAmqpManager();
    }

    public function testBindBindsExchangeToAnotherExchange(): void
    {
        $sourceExchange = new AMQPExchange($this->amqpChannel);
        $sourceExchange->setName('source-exchange-' . $this->uniqueTestIdentifier);
        $sourceExchange->setType(AMQP_EX_TYPE_FANOUT);
        $sourceExchange->declareExchange();

        // Bind the main exchange as the destination of the source exchange (E2E binding).
        static::assertTrue($this->amqpExchange->bind($sourceExchange->getName()));

        // Publish to source exchange; message should flow through to the main queue via the binding.
        $sourceExchange->publish('my message through binding');

        $envelope = $this->amqpQueue->get();
        static::assertInstanceOf(AMQPEnvelope::class, $envelope);
        static::assertSame('my message through binding', $envelope->getBody());

        $sourceExchange->delete();
    }

    public function testDeclareExchangeDeclaresExchangeOnBroker(): void
    {
        $exchange = new AMQPExchange($this->amqpChannel);
        $exchange->setName('new-exchange-' . $this->uniqueTestIdentifier);
        $exchange->setType(AMQP_EX_TYPE_DIRECT);

        static::assertTrue($exchange->declareExchange());

        $exchange->delete();
    }

    public function testDeleteExchangeDeletesExchangeFromBroker(): void
    {
        // Use a dedicated exchange so as not to affect the one cleaned up by ->tearDown().
        $exchange = new AMQPExchange($this->amqpChannel);
        $exchange->setName('exchange-to-delete-' . $this->uniqueTestIdentifier);
        $exchange->setType(AMQP_EX_TYPE_FANOUT);
        $exchange->declareExchange();

        static::assertTrue($exchange->delete());
    }

    public function testGetNameReturnsExchangeName(): void
    {
        static::assertSame('exchange-' . $this->uniqueTestIdentifier, $this->amqpExchange->getName());
    }

    public function testPublishPublishesMessageToQueue(): void
    {
        static::assertTrue($this->amqpExchange->publish('hello world'));

        $envelope = $this->amqpQueue->get();
        static::assertInstanceOf(AMQPEnvelope::class, $envelope);
        static::assertSame('hello world', $envelope->getBody());
    }

    public function testPublishWithAttributesSetsMessageProperties(): void
    {
        $this->amqpExchange->publish(
            'hello world',
            null,
            AMQP_NOPARAM,
            [
                'content_type' => 'application/json',
                'headers' => ['x-custom-header' => 'header-value'],
                'message_id' => 'my-message-id',
            ]
        );

        $envelope = $this->amqpQueue->get();
        static::assertInstanceOf(AMQPEnvelope::class, $envelope);
        static::assertSame('application/json', $envelope->getContentType());
        static::assertSame('header-value', $envelope->getHeader('x-custom-header'));
        static::assertSame('my-message-id', $envelope->getMessageId());
    }

    public function testPublishWithRoutingKeyDeliversMessageToCorrectQueue(): void
    {
        $directExchange = new AMQPExchange($this->amqpChannel);
        $directExchange->setName('direct-exchange-' . $this->uniqueTestIdentifier);
        $directExchange->setType(AMQP_EX_TYPE_DIRECT);
        $directExchange->declareExchange();

        $queue = new AMQPQueue($this->amqpChannel);
        $queue->setName('routed-queue-' . $this->uniqueTestIdentifier);
        $queue->setFlags(AMQP_NOPARAM);
        $queue->declareQueue();
        $queue->bind($directExchange->getName(), 'my-routing-key');

        $directExchange->publish('my routed message', 'my-routing-key');

        $envelope = $queue->get();
        static::assertInstanceOf(AMQPEnvelope::class, $envelope);
        static::assertSame('my routed message', $envelope->getBody());
        static::assertSame('my-routing-key', $envelope->getRoutingKey());

        $queue->delete();
        $directExchange->delete();
    }

    public function testUnbindUnbindsExchangeFromAnotherExchange(): void
    {
        $sourceExchange = new AMQPExchange($this->amqpChannel);
        $sourceExchange->setName('source-exchange-' . $this->uniqueTestIdentifier);
        $sourceExchange->setType(AMQP_EX_TYPE_FANOUT);
        $sourceExchange->declareExchange();

        // First bind, then unbind.
        $this->amqpExchange->bind($sourceExchange->getName());
        static::assertTrue($this->amqpExchange->unbind($sourceExchange->getName()));

        // After unbind, a message published to the source should _not_ arrive at the main queue.
        $sourceExchange->publish('this should not arrive');

        static::assertFalse($this->amqpQueue->get());

        $sourceExchange->delete();
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
}
