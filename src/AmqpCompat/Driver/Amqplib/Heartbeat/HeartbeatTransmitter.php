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

use AMQPException;
use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Heartbeat\HeartbeatTransmitterInterface;
use Asmblah\PhpAmqpCompat\Exception\HeartbeatMissedException;
use Asmblah\PhpAmqpCompat\Scheduler\Heartbeat\HeartbeatSchedulerInterface;

/**
 * Class HeartbeatTransmitter.
 *
 * Defines the way in which heartbeats are transmitted periodically.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class HeartbeatTransmitter implements HeartbeatTransmitterInterface
{
    /**
     * @inheritDoc
     */
    public function transmit(
        HeartbeatSchedulerInterface $heartbeatScheduler,
        AmqpConnectionBridgeInterface $connectionBridge
    ): void {
        if (!$connectionBridge->isConnected()) {
            // Connection is no longer open, so we cannot process heartbeats for it.
            $heartbeatScheduler->unregister($connectionBridge);
            return;
        }

        if ($connectionBridge->isBusy()) {
            // We're in the middle of writing data to the connection, don't interrupt with a heartbeat frame.
            return;
        }

        try {
            $connectionBridge->checkHeartbeat();
        } catch (HeartbeatMissedException $exception) {
            throw new AMQPException($exception->getMessage(), previous: $exception);
        }
    }
}
