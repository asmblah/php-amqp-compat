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
use AMQPExchangeException;
use AMQPQueue;
use AMQPQueueException;
use Asmblah\PhpAmqpCompat\Bridge\AmqpBridge;
use Asmblah\PhpAmqpCompat\Bridge\Channel\AmqpChannelBridgeInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Mockery;
use Mockery\MockInterface;
use PhpAmqpLib\Channel\AMQPChannel as AmqplibChannel;
use PhpAmqpLib\Connection\AbstractConnection as AmqplibConnection;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use PhpAmqpLib\Wire\AMQPTable as AmqplibTable;
use stdClass;

/**
 * Class AMQPQueueTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AMQPQueueTest extends AbstractTestCase
{
    /**
     * @var (MockInterface&AMQPChannel)|null
     */
    private $amqpChannel;
    private ?AMQPQueue $amqpQueue;
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

    public function setUp(): void
    {
        $this->amqpChannel = mock(AMQPChannel::class);
        $this->amqplibConnection = mock(AmqplibConnection::class, [
            'isConnected' => true,
        ]);
        $this->amqplibChannel = mock(AmqplibChannel::class, [
            'getConnection' => $this->amqplibConnection,
            'is_open' => true,
        ]);
        $this->channelBridge = mock(AmqpChannelBridgeInterface::class, [
            'getAmqplibChannel' => $this->amqplibChannel,
        ]);
        AmqpBridge::bridgeChannel($this->amqpChannel, $this->channelBridge);

        $this->amqpQueue = new AMQPQueue($this->amqpChannel);
    }

    public function testDeclareQueueDeclaresViaAmqplib(): void
    {
        $this->amqpQueue->setName('my_queue');
        $this->amqpQueue->setFlags(AMQP_PASSIVE | AMQP_EXCLUSIVE | AMQP_AUTODELETE);
        $this->amqpQueue->setArguments(['x-dead-letter-exchange' => 'my_retry_exchange']);

        $this->amqplibChannel->expects()
            ->queue_declare(
                'my_queue',
                true,
                false,
                true,
                true,
                false,
                Mockery::type(AmqplibTable::class)
            )
            ->once()
            ->andReturnUsing(function (
                $queueName,
                $passive,
                $durable,
                $exclusive,
                $autoDelete,
                $noWait,
                AmqplibTable $arguments
            ) {
                static::assertEquals(
                    ['x-dead-letter-exchange' => 'my_retry_exchange'],
                    $arguments->getNativeData()
                );

                $queueName = 'my_queue';
                $messageCount = 21;
                $consumerCount = 7;

                return [$queueName, $messageCount, $consumerCount];
            });

        static::assertSame(21, $this->amqpQueue->declareQueue(), 'Message count should be returned');
    }

    public function testDeclareQueueHandlesAmqplibExceptionCorrectly(): void
    {
        $this->amqpQueue->setName('my_queue');

        $this->amqplibChannel->allows()
            ->queue_declare(
                'my_queue',
                false,
                false,
                false,
                false,
                false,
                Mockery::type(AmqplibTable::class)
            )
            ->andThrow(new AMQPProtocolChannelException(21, 'my text', [1, 2, 3]));

        $this->expectException(AMQPExchangeException::class);
        $this->expectExceptionMessage('AMQPQueue::declareQueue(): Amqplib failure: my text');

        $this->amqpQueue->declareQueue();
    }

    public function testDeclareQueueHandlesWrongResultFromAmqplibCorrectly(): void
    {
        $this->amqpQueue->setName('my_queue');

        $this->amqplibChannel->allows()
            ->queue_declare(
                'my_queue',
                false,
                false,
                false,
                false,
                false,
                Mockery::type(AmqplibTable::class)
            )
            ->andReturn('I should be an array');

        $this->expectException(AMQPExchangeException::class);
        $this->expectExceptionMessage('AMQPQueue::declareQueue(): Amqplib result was not an array');

        $this->amqpQueue->declareQueue();
    }

    public function testGetArgumentReturnsTheSpecifiedArgumentValue(): void
    {
        $this->amqpQueue->setArgument('my_key', 'my value');

        static::assertSame('my value', $this->amqpQueue->getArgument('my_key'));
    }

    public function testGetArgumentFalseWhenSpecifiedArgumentIsNotSet(): void
    {
        static::assertFalse($this->amqpQueue->getArgument('an_undefined_key'));
    }

    public function testGetArgumentsReturnsAllArguments(): void
    {
        $this->amqpQueue->setArgument('my_first', 'first value');
        $this->amqpQueue->setArgument('my_second', 'second value');
        $this->amqpQueue->setArgument('my_third', 'third value');

        static::assertEquals(
            [
                'my_first' => 'first value',
                'my_second' => 'second value',
                'my_third' => 'third value',
            ],
            $this->amqpQueue->getArguments()
        );
    }

    public function testGetFlagsReturnsZeroInitially(): void
    {
        static::assertSame(0, $this->amqpQueue->getFlags());
    }

    public function testHasArgumentReturnsTrueForASetArgument(): void
    {
        $this->amqpQueue->setArgument('my_key', 21);

        static::assertTrue($this->amqpQueue->hasArgument('my_key'));
    }

    public function testHasArgumentReturnsFalseForAnUnsetArgument(): void
    {
        $this->amqpQueue->setArgument('my_key', 21);

        static::assertFalse($this->amqpQueue->hasArgument('not_my_key'));
    }

    public function testSetArgumentThrowsWhenGivenInvalidValue(): void
    {
        $this->expectException(AMQPQueueException::class);
        $this->expectExceptionMessage('The value parameter must be of type NULL, int, double or string.');

        $this->amqpQueue->setArgument('my_key', new stdClass);
    }

    public function testSetArgumentsSetsAllGivenArguments(): void
    {
        $this->amqpQueue->setArguments(['first_key' => 21, 'second_key' => 'my value']);

        static::assertEquals(
            ['first_key' => 21, 'second_key' => 'my value'],
            $this->amqpQueue->getArguments()
        );
    }

    public function testSetFlagsSetsAllGivenFlags(): void
    {
        $this->amqpQueue->setFlags(AMQP_AUTODELETE | AMQP_EXCLUSIVE | AMQP_PASSIVE);

        static::assertSame(AMQP_AUTODELETE | AMQP_EXCLUSIVE | AMQP_PASSIVE, $this->amqpQueue->getFlags());
    }

    public function testSetFlagsCanSetNowaitFlagAlone(): void
    {
        $this->amqpQueue->setFlags(AMQP_NOWAIT);

        static::assertSame(AMQP_NOWAIT, $this->amqpQueue->getFlags());
    }
}
