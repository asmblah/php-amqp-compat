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
use LogicException;

class Consumer implements ConsumerInterface
{
    /**
     * Registered callback function.
     * NB: Cannot be typed as `callable`.
     *
     * @var callable|null
     */
    private $consumptionCallback = null;

    /**
     * @inheritDoc
     */
    public function getConsumptionCallback(): callable
    {
        return $this->consumptionCallback;
    }

    /**
     * @inheritDoc
     */
    public function consumeEnvelope(AMQPEnvelope $amqpEnvelope, AMQPQueue $amqpQueue): void
    {
        if (!$this->consumptionCallback) {
            throw new LogicException(__METHOD__ . ' :: No callback is registered');
        }

        $result = ($this->consumptionCallback)($amqpEnvelope, $amqpQueue);

        if ($result === false) {
            throw new StopConsumptionException();
        }
    }

    /**
     * @inheritDoc
     */
    public function setConsumptionCallback(?callable $callback): void
    {
        $this->consumptionCallback = $callback;
    }
}
