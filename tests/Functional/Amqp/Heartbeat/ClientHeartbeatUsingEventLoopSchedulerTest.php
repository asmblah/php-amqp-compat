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

namespace Asmblah\PhpAmqpCompat\Tests\Functional\Amqp\Heartbeat;

use AMQPConnection;
use Asmblah\PhpAmqpCompat\AmqpManager;
use Asmblah\PhpAmqpCompat\Configuration\Configuration;
use Asmblah\PhpAmqpCompat\Heartbeat\HeartbeatSchedulerMode;
use Asmblah\PhpAmqpCompat\Tests\Functional\AbstractFunctionalTestCase;
use PhpAmqpLib\Exception\AMQPHeartbeatMissedException;
use Tasque\EventLoop\TasqueEventLoop;

/**
 * Class ClientHeartbeatUsingEventLoopSchedulerTest.
 *
 * Checks connection heartbeat handling when the client fails to send its own heartbeats
 * nor check for server heartbeats on a real connection to a real AMQP broker server
 * when EventLoopHeartbeatScheduler is in use.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ClientHeartbeatUsingEventLoopSchedulerTest extends AbstractFunctionalTestCase
{
    public function setUp(): void
    {
        parent::setUp();

        AmqpManager::setAmqpIntegration(null);
        AmqpManager::setConfiguration(new Configuration(
            heartbeatSchedulerMode: HeartbeatSchedulerMode::EVENT_LOOP
        ));
    }

    public function tearDown(): void
    {
        parent::tearDown();

        AmqpManager::setAmqpIntegration(null);
        AmqpManager::setConfiguration(null);
    }

    public function testMissedClientHeartbeatIsHandledCorrectly(): void
    {
        $amqpConnection = new AMQPConnection(['heartbeat' => 1]);
        $amqpConnection->connect();

        $this->expectException(AMQPHeartbeatMissedException::class);

        // Block heartbeats from being processed.
        sleep(5);

        TasqueEventLoop::getEventLoopThread()->join();
    }
}
