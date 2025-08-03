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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\AmqpCompat\Heartbeat;

use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Heartbeat\HeartbeatSender;
use Asmblah\PhpAmqpCompat\Scheduler\Heartbeat\HeartbeatSchedulerInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Mockery\MockInterface;

/**
 * Class HeartbeatSenderTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class HeartbeatSenderTest extends AbstractTestCase
{
    private MockInterface&AmqpConnectionBridgeInterface $connectionBridge;
    private MockInterface&HeartbeatSchedulerInterface $heartbeatScheduler;
    private HeartbeatSender $heartbeatSender;

    public function setUp(): void
    {
        $this->connectionBridge = mock(AmqpConnectionBridgeInterface::class);
        $this->heartbeatScheduler = mock(HeartbeatSchedulerInterface::class);

        $this->heartbeatSender = new HeartbeatSender($this->heartbeatScheduler);
    }

    /**
     * @dataProvider heartbeatIntervalDataProvider
     */
    public function testRegisterRegistersConnectionWhenHeartbeatIntervalIsNonZero(int $interval): void
    {
        $this->connectionBridge->expects('getHeartbeatInterval')
            ->andReturn($interval);

        $this->heartbeatScheduler->expects()
            ->register($this->connectionBridge)
            ->once();

        $this->heartbeatSender->register($this->connectionBridge);
    }

    /**
     * @return array<mixed>
     */
    public static function heartbeatIntervalDataProvider(): array
    {
        return [
            'one second interval' => [1],
            'thirty seconds interval' => [30],
        ];
    }

    public function testRegisterDoesNotRegisterConnectionWhenHeartbeatIntervalIsZero(): void
    {
        $this->connectionBridge->expects('getHeartbeatInterval')
            ->andReturn(0);

        $this->heartbeatScheduler->expects('register')
            ->never();

        $this->heartbeatSender->register($this->connectionBridge);
    }

    public function testUnregisterUnregistersConnection(): void
    {
        $this->heartbeatScheduler->expects()
            ->unregister($this->connectionBridge)
            ->once();

        $this->heartbeatSender->unregister($this->connectionBridge);
    }
}
