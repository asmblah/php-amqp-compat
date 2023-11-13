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

namespace Asmblah\PhpAmqpCompat\Driver\Amqplib\Heartbeat;

use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Heartbeat\HeartbeatTransmitterInterface;
use Asmblah\PhpAmqpCompat\Misc\ClockInterface;
use Asmblah\PhpAmqpCompat\Scheduler\Heartbeat\HeartbeatSchedulerInterface;

/**
 * Class HeartbeatTransmitter.
 *
 * Defines the way in which heartbeats are transmitted.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class HeartbeatTransmitter implements HeartbeatTransmitterInterface
{
    public function __construct(
        private readonly ClockInterface $clock
    ) {
    }

    /**
     * @inheritDoc
     */
    public function transmit(
        HeartbeatSchedulerInterface $heartbeatScheduler,
        AmqpConnectionBridgeInterface $connectionBridge
    ): void {
        $now = $this->clock->getUnixTimestamp();

        $amqplibConnection = $connectionBridge->getAmqplibConnection();
        $interval = $connectionBridge->getHeartbeatInterval();

        if (!$amqplibConnection->isConnected()) {
            // Connection is no longer open, so we cannot process heartbeats for it.
            $heartbeatScheduler->unregister($connectionBridge);
            return;
        }

        if ($amqplibConnection->isWriting()) {
            // We're in the middle of writing data to the connection, don't interrupt with a heartbeat frame.
            return;
        }

        if ($now > ($amqplibConnection->getLastActivity() + $interval)) {
            $amqplibConnection->checkHeartBeat();
        }
    }
}
