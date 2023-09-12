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

use AMQPEnvelope;
use AMQPQueue;
use Asmblah\PhpAmqpCompat\Exception\StopConsumptionException;

interface ConsumerInterface
{
    /**
     * Consumes the given envelope, raising a StopConsumptionException
     * if further consumption should be stopped.
     *
     * @throws StopConsumptionException
     */
    public function consumeEnvelope(AMQPEnvelope $amqpEnvelope, AMQPQueue $amqpQueue): void;

    /**
     * Fetches the callback to use for consuming AMQP messages, if any.
     */
    public function getConsumptionCallback(): callable;

    /**
     * Sets the callback to use for consuming AMQP messages.
     */
    public function setConsumptionCallback(callable $callback): void;
}
