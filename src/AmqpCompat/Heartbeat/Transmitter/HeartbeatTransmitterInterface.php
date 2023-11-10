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

namespace Asmblah\PhpAmqpCompat\Heartbeat\Transmitter;

use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Heartbeat\Scheduler\HeartbeatSchedulerInterface;

/**
 * Interface HeartbeatTransmitterInterface.
 *
 * Defines the way in which heartbeats are transmitted.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface HeartbeatTransmitterInterface
{
    /**
     * Transmits a heartbeat.
     */
    public function transmit(
        HeartbeatSchedulerInterface $heartbeatScheduler,
        AmqpConnectionBridgeInterface $connectionBridge
    ): void;
}
