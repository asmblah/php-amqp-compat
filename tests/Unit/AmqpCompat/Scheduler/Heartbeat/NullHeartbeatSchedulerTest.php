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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\AmqpCompat\Scheduler\Heartbeat;

use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Scheduler\Heartbeat\NullHeartbeatScheduler;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Mockery\MockInterface;

/**
 * Class NullHeartbeatSchedulerTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class NullHeartbeatSchedulerTest extends AbstractTestCase
{
    private MockInterface&AmqpConnectionBridgeInterface $connectionBridge;
    private NullHeartbeatScheduler $scheduler;

    public function setUp(): void
    {
        $this->connectionBridge = mock(AmqpConnectionBridgeInterface::class);

        $this->scheduler = new NullHeartbeatScheduler();
    }

    public function testRegisterDoesNothing(): void
    {
        $this->expectNotToPerformAssertions();

        $this->scheduler->register($this->connectionBridge);
    }

    public function testUnregisterDoesNothing(): void
    {
        $this->expectNotToPerformAssertions();

        $this->scheduler->unregister($this->connectionBridge);
    }

    public function testCanRegisterAndUnregisterMultipleTimes(): void
    {
        $this->expectNotToPerformAssertions();

        // Test that multiple calls don't cause issues.
        $this->scheduler->register($this->connectionBridge);
        $this->scheduler->register($this->connectionBridge);
        $this->scheduler->unregister($this->connectionBridge);
        $this->scheduler->unregister($this->connectionBridge);
    }
}
