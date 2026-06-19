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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\AmqpCompat\Driver\Amqplib\Heartbeat;

use AMQPException;
use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Heartbeat\HeartbeatTransmitter;
use Asmblah\PhpAmqpCompat\Exception\HeartbeatMissedException;
use Asmblah\PhpAmqpCompat\Scheduler\Heartbeat\HeartbeatSchedulerInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Mockery\MockInterface;

/**
 * Class HeartbeatTransmitterTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class HeartbeatTransmitterTest extends AbstractTestCase
{
    private MockInterface&AmqpConnectionBridgeInterface $connectionBridge;
    private MockInterface&HeartbeatSchedulerInterface $heartbeatScheduler;
    private HeartbeatTransmitter $heartbeatTransmitter;

    public function setUp(): void
    {
        $this->connectionBridge = mock(AmqpConnectionBridgeInterface::class, [
            'checkHeartbeat' => null,
            'isBusy' => false,
            'isConnected' => true,
        ]);
        $this->heartbeatScheduler = mock(HeartbeatSchedulerInterface::class);

        $this->heartbeatTransmitter = new HeartbeatTransmitter();
    }

    public function testTransmitUnregistersConnectionWhenNoLongerConnected(): void
    {
        $this->connectionBridge->allows('isConnected')
            ->andReturn(false);

        $this->heartbeatScheduler->expects()
            ->unregister($this->connectionBridge)
            ->once();

        $this->heartbeatTransmitter->transmit($this->heartbeatScheduler, $this->connectionBridge);
    }

    public function testTransmitDoesNotCheckHeartbeatWhenNoLongerConnected(): void
    {
        $this->connectionBridge->allows('isConnected')
            ->andReturn(false);
        $this->heartbeatScheduler->allows('unregister');

        $this->connectionBridge->expects('checkHeartbeat')
            ->never();

        $this->heartbeatTransmitter->transmit($this->heartbeatScheduler, $this->connectionBridge);
    }

    public function testTransmitDoesNothingWhenConnectionIsBusy(): void
    {
        $this->connectionBridge->allows('isBusy')
            ->andReturnTrue();

        $this->heartbeatScheduler->expects('unregister')
            ->never();
        $this->connectionBridge->expects('checkHeartbeat')
            ->never();

        $this->heartbeatTransmitter->transmit($this->heartbeatScheduler, $this->connectionBridge);
    }

    public function testTransmitChecksHeartbeatWhenConnectedAndNotBusy(): void
    {
        $this->connectionBridge->expects('checkHeartbeat')
            ->once();

        $this->heartbeatTransmitter->transmit($this->heartbeatScheduler, $this->connectionBridge);
    }

    public function testTransmitThrowsAmqpExceptionWhenHeartbeatIsMissed(): void
    {
        $this->connectionBridge->allows('checkHeartbeat')
            ->andThrow(new HeartbeatMissedException('Heartbeat missed'));

        $this->expectException(AMQPException::class);
        $this->expectExceptionMessage('Heartbeat missed');

        $this->heartbeatTransmitter->transmit($this->heartbeatScheduler, $this->connectionBridge);
    }
}