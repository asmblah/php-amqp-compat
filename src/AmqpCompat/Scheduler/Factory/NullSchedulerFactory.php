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
use Asmblah\PhpAmqpCompat\Scheduler\Heartbeat\NullHeartbeatScheduler;

/**
 * Class NullSchedulerFactory.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class NullSchedulerFactory implements SchedulerFactoryInterface
{
    /**
     * @inheritDoc
     */
    public function createScheduler(HeartbeatTransmitterInterface $heartbeatTransmitter): HeartbeatSchedulerInterface
    {
        return new NullHeartbeatScheduler();
    }
}
