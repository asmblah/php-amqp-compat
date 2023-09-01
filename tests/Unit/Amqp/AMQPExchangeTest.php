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
use AMQPExchange;
use AMQPExchangeException;
use Asmblah\PhpAmqpCompat\Bridge\AmqpBridge;
use Asmblah\PhpAmqpCompat\Bridge\Channel\AmqpChannelBridgeInterface;
use Asmblah\PhpAmqpCompat\Logger\LoggerInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Mockery;
use Mockery\MockInterface;
use PhpAmqpLib\Channel\AMQPChannel as AmqplibChannel;
use PhpAmqpLib\Connection\AbstractConnection as AmqplibConnection;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use PhpAmqpLib\Message\AMQPMessage as AmqplibMessage;
use PhpAmqpLib\Wire\AMQPTable as AmqplibTable;

/**
 * Class AMQPExchangeTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AMQPExchangeTest extends AbstractTestCase
{
    /**
     * @var (MockInterface&AMQPChannel)|null
     */
    private $amqpChannel;
    private ?AMQPExchange $amqpExchange;
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
     * @var (MockInterface&LoggerInterface)|null
     */
    private $logger;

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
        $this->logger = mock(LoggerInterface::class, [
            'debug' => null,
        ]);
        $this->channelBridge = mock(AmqpChannelBridgeInterface::class, [
            'getAmqplibChannel' => $this->amqplibChannel,
            'getLogger' => $this->logger,
        ]);
        AmqpBridge::bridgeChannel($this->amqpChannel, $this->channelBridge);

        $this->amqpExchange = new AMQPExchange($this->amqpChannel);
    }

    public function testConstructorNotBeingCalledIsHandledCorrectly(): void
    {
        $extendedAmqpExchange = new class($this->amqpChannel) extends AMQPExchange {
            public function __construct(AMQPChannel $amqpChannel)
            {
                // Deliberately omit the call to the super constructor.
            }
        };

        $this->expectException(AMQPChannelException::class);
        $this->expectExceptionMessage('Could not declare exchange. Stale reference to the channel object.');

        $extendedAmqpExchange->declareExchange();
    }

    public function testDeclareExchangeDeclaresViaAmqplib(): void
    {
        $this->amqpExchange->setName('my_exchange');
        $this->amqpExchange->setType(AMQP_EX_TYPE_FANOUT);
        $this->amqpExchange->setFlags(AMQP_PASSIVE | AMQP_AUTODELETE | AMQP_INTERNAL);
        $this->amqpExchange->setArguments(['x-my-arg' => 'my value']);

        $this->amqplibChannel->expects()
            ->exchange_declare(
                'my_exchange',
                AMQP_EX_TYPE_FANOUT,
                true,
                false,
                true,
                true,
                false,
                Mockery::type(AmqplibTable::class)
            )
            ->once()
            ->andReturnUsing(function (
                $exchangeName,
                $type,
                $passive,
                $durable,
                $autoDelete,
                $internal,
                $noWait,
                AmqplibTable $arguments
            ) {
                static::assertEquals(
                    ['x-my-arg' => 'my value'],
                    $arguments->getNativeData()
                );
            });

        $this->amqpExchange->declareExchange();
    }

    public function testDeclareExchangeHandlesAmqplibExceptionCorrectly(): void
    {
        $this->amqpExchange->setName('my_exchange');
        $this->amqpExchange->setType(AMQP_EX_TYPE_FANOUT);
        $exception = new AMQPProtocolChannelException(21, 'my text', [1, 2, 3]);

        $this->amqplibChannel->allows()
            ->exchange_declare(
                'my_exchange',
                AMQP_EX_TYPE_FANOUT,
                false,
                false,
                false,
                false,
                false,
                Mockery::type(AmqplibTable::class)
            )
            ->andThrow($exception);

        $this->expectException(AMQPExchangeException::class);
        $this->expectExceptionMessage('Server channel error: 21, message: my text');
        $this->logger->expects()
            ->logAmqplibException('AMQPExchange::declareExchange', $exception)
            ->once();

        $this->amqpExchange->declareExchange();
    }

    public function testPublishPublishesViaAmqplibWhenGivenMessageOnly(): void
    {
        $this->amqpExchange->setName('my_exchange');
        $this->amqplibChannel->expects()
            ->basic_publish(
                Mockery::type(AmqplibMessage::class),
                'my_exchange',
                null,
                false,
                false
            )
            ->once();

        $this->amqpExchange->publish('my message');
    }

    public function testPublishSetsDefaultContentTypeIfNeeded(): void
    {
        $this->amqpExchange->setName('my_exchange');
        $this->amqplibChannel->expects()
            ->basic_publish(Mockery::andAnyOtherArgs())
            ->once()
            ->andReturnUsing(function (AmqplibMessage $amqpMessage) {
                static::assertSame('text/plain', $amqpMessage->get_properties()['content_type']);
            });

        $this->amqpExchange->publish('my message');
    }
}
