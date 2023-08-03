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

namespace Asmblah\PhpAmqpCompat\Bridge\Channel;

use PhpAmqpLib\Message\AMQPMessage as AmqplibMessage;

interface ConsumerInterface
{
    /**
     * Consumes the given message, returning false if further consumption should be stopped.
     */
    public function consumeMessage(AmqplibMessage $message): void;

    /**
     * Fetches the callback to use for consuming AMQP messages, if any.
     */
    public function getConsumptionCallback(): callable;

    /**
     * Sets the callback to use for consuming AMQP messages.
     */
    public function setConsumptionCallback(callable $callback): void;
}
