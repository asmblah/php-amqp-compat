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

namespace Asmblah\PhpAmqpCompat\Tests\Functional\Memory;

use AMQPChannel;
use AMQPConnection;
use AMQPExchange;
use AMQPQueue;
use Asmblah\PhpAmqpCompat\AmqpManager;
use Asmblah\PhpAmqpCompat\Configuration\Configuration;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class PublishingMemoryConsumptionTest.
 *
 * Ensures that the publishing mechanism introduces no memory leaks.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class PublishingMemoryConsumptionTest extends AbstractTestCase
{
    private AMQPConnection $amqpConnection;
    private AMQPChannel $amqpChannel;
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

    public function testNoMemoryIsLeakedDuringPublishing(): void
    {
        gc_collect_cycles();
        $consumptionInBytesBefore = memory_get_usage(true);

        for ($i = 0; $i < 10000; $i++) {
            $this->amqpExchange->publish('my message body');
        }

        gc_collect_cycles();
        $consumptionInBytesAfter = memory_get_usage(true);

        static::assertLessThan(
            1024 * 1024,
            $consumptionInBytesAfter - $consumptionInBytesBefore,
            'Consumption should not increase by more than 1 MiB'
        );
    }
}
