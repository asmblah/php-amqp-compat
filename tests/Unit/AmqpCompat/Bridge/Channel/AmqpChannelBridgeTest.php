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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\AmqpCompat\Bridge\Channel;

use Asmblah\PhpAmqpCompat\Bridge\Channel\AmqpChannelBridge;
use Asmblah\PhpAmqpCompat\Bridge\Channel\ConsumerInterface;
use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Mockery\MockInterface;
use PhpAmqpLib\Channel\AMQPChannel as AmqplibChannel;

/**
 * Class AmqpChannelBridgeTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqpChannelBridgeTest extends AbstractTestCase
{
    /**
     * @var (MockInterface&AmqplibChannel)|null
     */
    private $amqplibChannel;
    private ?AmqpChannelBridge $channelBridge;
    /**
     * @var (MockInterface&AmqpConnectionBridgeInterface)|null
     */
    private $connectionBridge;
    /**
     * @var (MockInterface&ConsumerInterface)|null
     */
    private $consumer;

    public function setUp(): void
    {
        $this->amqplibChannel = mock(AmqplibChannel::class);
        $this->connectionBridge = mock(AmqpConnectionBridgeInterface::class);
        $this->consumer = mock(ConsumerInterface::class);

        $this->channelBridge = new AmqpChannelBridge(
            $this->connectionBridge,
            $this->amqplibChannel,
            $this->consumer
        );
    }

    public function testUnregisterChannelUnregistersChannelBridgeViaConnectionBridge(): void
    {
        $this->connectionBridge->expects()
            ->unregisterChannelBridge($this->channelBridge)
            ->once();

        $this->channelBridge->unregisterChannel();
    }
}
