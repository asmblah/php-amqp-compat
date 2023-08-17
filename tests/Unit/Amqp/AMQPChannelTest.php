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
use AMQPConnection;
use Asmblah\PhpAmqpCompat\Bridge\AmqpBridge;
use Asmblah\PhpAmqpCompat\Bridge\Channel\AmqpChannelBridgeInterface;
use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Mockery\MockInterface;
use PhpAmqpLib\Channel\AMQPChannel as AmqplibChannel;
use PhpAmqpLib\Connection\AbstractConnection as AmqplibConnection;

/**
 * Class AMQPChannelTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AMQPChannelTest extends AbstractTestCase
{
    private ?AMQPChannel $amqpChannel;
    /**
     * @var (MockInterface&AMQPConnection)|null
     */
    private $amqpConnection;
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
     * @var (MockInterface&AmqpConnectionBridgeInterface)|null
     */
    private $connectionBridge;

    public function setUp(): void
    {
        $this->amqpConnection = mock(AMQPConnection::class);
        $this->amqplibChannel = mock(AmqplibChannel::class, [
            'close' => null,
            'is_open' => true,
        ]);
        $this->amqplibConnection = mock(AmqplibConnection::class);
        $this->channelBridge = mock(AmqpChannelBridgeInterface::class, [
            'getAmqplibChannel' => $this->amqplibChannel,
            'unregisterChannel' => null,
        ]);
        $this->connectionBridge = mock(AmqpConnectionBridgeInterface::class, [
            'getAmqplibConnection' => $this->amqplibConnection,
            'createChannelBridge' => $this->channelBridge,
        ]);
        AmqpBridge::bridgeConnection($this->amqpConnection, $this->connectionBridge);

        $this->amqpChannel = new AMQPChannel($this->amqpConnection);
    }

    public function testConstructorCorrectlyBridgesTheChannelToTheCreatedChannelBridge(): void
    {
        $this->connectionBridge->expects()
            ->createChannelBridge($this->connectionBridge)
            ->once()
            ->andReturn($this->channelBridge);

        new AMQPChannel($this->amqpConnection);

        static::assertSame($this->channelBridge, AmqpBridge::getBridgeChannel($this->amqpChannel));
    }

    public function testDestructorClosesChannelWhenOpen(): void
    {
        $this->amqplibChannel->expects()
            ->close()
            ->once();

        $this->amqpChannel = null; // Invoke the destructor synchronously (assuming no reference cycles).
    }

    public function testDestructorUnregistersChannel(): void
    {
        $this->channelBridge->expects()
            ->unregisterChannel()
            ->once();

        $this->amqpChannel = null; // Invoke the destructor synchronously (assuming no reference cycles).
    }

    public function testDestructorDoesNotCloseChannelWhenClosed(): void
    {
        $this->amqplibChannel->allows()
            ->is_open()
            ->andReturn(false);

        $this->amqplibChannel->expects()
            ->close()
            ->never();

        $this->amqpChannel = null; // Invoke the destructor synchronously (assuming no reference cycles).
    }
}
