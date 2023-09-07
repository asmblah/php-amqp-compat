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
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use PhpAmqpLib\Exception\AMQPHeartbeatMissedException;

/**
 * Class ClientHeartbeatTest.
 *
 * Checks connection heartbeat handling when the client fails to send its own heartbeats
 * nor check for server heartbeats on a real connection to a real AMQP broker server.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ClientHeartbeatTest extends AbstractTestCase
{
    public function testMissedClientHeartbeatIsHandledCorrectly(): void
    {
        $amqpConnection = new AMQPConnection(['heartbeat' => 1]);
        $amqpConnection->connect();

        $this->expectException(AMQPHeartbeatMissedException::class);

        // Use time_sleep_until(...) so that the SIGALRM signals don't prevent the full sleep.
        time_sleep_until(microtime(true) + 5);
    }
}
