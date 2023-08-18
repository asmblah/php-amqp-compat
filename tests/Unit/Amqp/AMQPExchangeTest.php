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
use AMQPExchange;
use AMQPExchangeException;
use Asmblah\PhpAmqpCompat\Bridge\AmqpBridge;
use Asmblah\PhpAmqpCompat\Bridge\Channel\AmqpChannelBridgeInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Mockery;
use Mockery\MockInterface;
use PhpAmqpLib\Channel\AMQPChannel as AmqplibChannel;
use PhpAmqpLib\Connection\AbstractConnection as AmqplibConnection;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
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

        $this->amqpExchange = new AMQPExchange($this->amqpChannel);
    }

    public function testDeclareExchangeDeclaresViaAmqplib(): void
    {
        $this->amqpExchange->setName('my_exchange');
        $this->amqpExchange->setType(AMQP_EX_TYPE_FANOUT);
        $this->amqpExchange->setFlags(AMQP_PASSIVE | AMQP_AUTODELETE | AMQP_INTERNAL);
        $this->amqpExchange->setArguments(['x-dead-letter-exchange' => 'my_retry_exchange']);

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
                    ['x-dead-letter-exchange' => 'my_retry_exchange'],
                    $arguments->getNativeData()
                );
            });

        $this->amqpExchange->declareExchange();
    }

    public function testDeclareExchangeHandlesAmqplibExceptionCorrectly(): void
    {
        $this->amqpExchange->setName('my_exchange');
        $this->amqpExchange->setType(AMQP_EX_TYPE_FANOUT);

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
            ->andThrow(new AMQPProtocolChannelException(21, 'my text', [1, 2, 3]));

        $this->expectException(AMQPExchangeException::class);
        $this->expectExceptionMessage('Server channel error: 21, message: my text');

        $this->amqpExchange->declareExchange();
    }
}
