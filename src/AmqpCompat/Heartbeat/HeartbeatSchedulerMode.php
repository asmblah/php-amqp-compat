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

/**
 * Enum HeartbeatSchedulerMode.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
enum HeartbeatSchedulerMode
{
    /**
     * Specifies that the ReactPHP event loop -based scheduler should be used.
     */
    case EVENT_LOOP;

    /**
     * Specifies that the pcntl/System V signals -based scheduler should be used.
     */
    case PCNTL;
}
