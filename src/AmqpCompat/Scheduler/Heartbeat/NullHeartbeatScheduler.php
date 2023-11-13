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

namespace Asmblah\PhpAmqpCompat\Scheduler\Heartbeat;

use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;

/**
 * Class NullHeartbeatScheduler.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class NullHeartbeatScheduler implements HeartbeatSchedulerInterface
{
    /**
     * @inheritDoc
     */
    public function register(AmqpConnectionBridgeInterface $connectionBridge): void
    {
        // Nothing to do.
    }

    /**
     * @inheritDoc
     */
    public function unregister(AmqpConnectionBridgeInterface $connectionBridge): void
    {
        // Nothing to do.
    }
}
