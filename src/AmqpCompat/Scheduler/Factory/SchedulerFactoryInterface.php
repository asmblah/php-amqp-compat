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

namespace Asmblah\PhpAmqpCompat\Scheduler\Factory;

use Asmblah\PhpAmqpCompat\Driver\Common\Heartbeat\HeartbeatTransmitterInterface;
use Asmblah\PhpAmqpCompat\Scheduler\Heartbeat\HeartbeatSchedulerInterface;

/**
 * Interface SchedulerFactoryInterface.
 *
 * The scheduler is responsible for periodic logic such as sending heartbeats.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface SchedulerFactoryInterface
{
    /**
     * Creates the heartbeat scheduler for the sender.
     */
    public function createScheduler(HeartbeatTransmitterInterface $heartbeatTransmitter): HeartbeatSchedulerInterface;
}
