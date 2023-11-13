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

namespace Asmblah\PhpAmqpCompat\Heartbeat;

use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Scheduler\Heartbeat\HeartbeatSchedulerInterface;

/**
 * Class HeartbeatSender.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class HeartbeatSender implements HeartbeatSenderInterface
{
    public function __construct(
        private readonly HeartbeatSchedulerInterface $heartbeatScheduler
    ) {
    }

    /**
     * @inheritDoc
     */
    public function register(AmqpConnectionBridgeInterface $connectionBridge): void
    {
        $interval = $connectionBridge->getHeartbeatInterval();

        if ($interval === 0) {
            // Heartbeats are not enabled for the connection.
            return;
        }

        $this->heartbeatScheduler->register($connectionBridge);
    }

    /**
     * @inheritDoc
     */
    public function unregister(AmqpConnectionBridgeInterface $connectionBridge): void
    {
        $this->heartbeatScheduler->unregister($connectionBridge);
    }
}
