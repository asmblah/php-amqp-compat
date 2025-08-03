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

use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Heartbeat\HeartbeatTransmitter;
use Asmblah\PhpAmqpCompat\Misc\ClockInterface;
use Asmblah\PhpAmqpCompat\Scheduler\Heartbeat\HeartbeatSchedulerInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Mockery\MockInterface;
use PhpAmqpLib\Connection\AbstractConnection;

/**
 * Class HeartbeatTransmitterTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class HeartbeatTransmitterTest extends AbstractTestCase
{
    private MockInterface&AbstractConnection $amqplibConnection;
    private MockInterface&ClockInterface $clock;
    private MockInterface&AmqpConnectionBridgeInterface $connectionBridge;
    private MockInterface&HeartbeatSchedulerInterface $heartbeatScheduler;
    private HeartbeatTransmitter $heartbeatTransmitter;

    public function setUp(): void
    {
        $this->amqplibConnection = mock(AbstractConnection::class, [
            'isConnected' => true,
            'isWriting' => false,
        ]);
        $this->clock = mock(ClockInterface::class, [
            'getUnixTimestamp' => 1000,
        ]);
        $this->connectionBridge = mock(AmqpConnectionBridgeInterface::class, [
            'getAmqplibConnection' => $this->amqplibConnection,
            'getHeartbeatInterval' => 60,
        ]);
        $this->heartbeatScheduler = mock(HeartbeatSchedulerInterface::class);

        $this->heartbeatTransmitter = new HeartbeatTransmitter($this->clock);
    }

    public function testTransmitUnregistersConnectionWhenNoLongerConnected(): void
    {
        $this->amqplibConnection->allows('isConnected')
            ->andReturn(false);

        $this->heartbeatScheduler->expects()
            ->unregister($this->connectionBridge)
            ->once();

        $this->heartbeatTransmitter->transmit($this->heartbeatScheduler, $this->connectionBridge);
    }

    public function testTransmitDoesNothingWhenConnectionIsWriting(): void
    {
        $this->amqplibConnection->allows('isWriting')
            ->andReturnTrue();

        $this->heartbeatScheduler->expects('unregister')
            ->never();
        $this->amqplibConnection->expects('checkHeartBeat')
            ->never();

        $this->heartbeatTransmitter->transmit($this->heartbeatScheduler, $this->connectionBridge);
    }

    public function testTransmitChecksHeartbeatWhenEnoughTimeHasPassed(): void
    {
        $this->amqplibConnection->allows('getLastActivity')
            ->andReturn(900);

        $this->amqplibConnection->expects('checkHeartBeat')
            ->once();

        $this->heartbeatTransmitter->transmit($this->heartbeatScheduler, $this->connectionBridge);
    }

    public function testTransmitDoesNotCheckHeartbeatWhenNotEnoughTimeHasPassed(): void
    {
        $this->amqplibConnection->allows('getLastActivity')
            ->andReturn(950);

        $this->amqplibConnection->expects('checkHeartBeat')
            ->never();

        $this->heartbeatTransmitter->transmit($this->heartbeatScheduler, $this->connectionBridge);
    }

    /**
     * @dataProvider heartbeatTimingDataProvider
     */
    public function testTransmitHandlesVariousTimingScenarios(
        int $currentTime,
        int $lastActivity,
        int $interval,
        bool $shouldCheckHeartbeat
    ): void {
        $this->clock->allows('getUnixTimestamp')
            ->andReturn($currentTime);
        $this->connectionBridge->allows('getHeartbeatInterval')
            ->andReturn($interval);
        $this->amqplibConnection->allows('getLastActivity')
            ->andReturn($lastActivity);

        $this->amqplibConnection->expects('checkHeartBeat')
            ->times($shouldCheckHeartbeat ? 1 : 0);

        $this->heartbeatTransmitter->transmit($this->heartbeatScheduler, $this->connectionBridge);
    }

    /**
     * @return array<mixed>
     */
    public static function heartbeatTimingDataProvider(): array
    {
        return [
            'exactly at interval boundary' => [1060, 1000, 60, false],
            'one second past interval' => [1061, 1000, 60, true],
            'one second before interval' => [1059, 1000, 60, false],
            'zero interval' => [1000, 900, 0, true],
            'large interval not reached' => [2000, 1000, 2000, false],
            'large interval reached' => [3001, 1000, 2000, true],
        ];
    }
}
