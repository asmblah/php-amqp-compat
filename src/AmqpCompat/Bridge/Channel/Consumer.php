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
use Asmblah\PhpAmqpCompat\Exception\StopConsumptionException;
use LogicException;
use PhpAmqpLib\Message\AMQPMessage as AmqplibMessage;
use PhpAmqpLib\Wire\AMQPTable as AmqplibTable;

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
    public function consumeMessage(AmqplibMessage $message): void
    {
        if (!$this->consumptionCallback) {
            throw new LogicException(__METHOD__ . ' :: No callback is registered');
        }

        $properties = $message->get_properties();

        $applicationHeadersTable = $properties['application_headers'] ?? null;

        if ($applicationHeadersTable === null) {
            $headers = [];
        } elseif ($applicationHeadersTable instanceof AmqplibTable) {
            $headers = $applicationHeadersTable->getNativeData();
        } else {
            throw new LogicException(__METHOD__ . ' :: application_headers is not an AMQPTable');
        }

        $amqpEnvelope = new AMQPEnvelope(
            $message->getBody(),
            $message->getConsumerTag(),
            $message->getDeliveryTag(),
            $message->getExchange(),
            $message->isRedelivered(),
            $message->getRoutingKey(),
            $properties['content_type'] ?? '',
            $message->getContentEncoding() ?? '',
            $headers,
            $properties['delivery_mode'] ?? AMQP_DELIVERY_MODE_TRANSIENT,
            $properties['priority'] ?? 0,
            $properties['correlation_id'] ?? '',
            $properties['reply_to'] ?? '',
            $properties['expiration'] ?? '',
            $properties['message_id'] ?? '',
            $properties['timestamp'] ?? 0,
            $properties['type'] ?? '',
            $properties['user_id'] ?? '',
            $properties['app_id'] ?? '',
            $properties['cluster_id'] ?? ''
        );

        $result = ($this->consumptionCallback)($amqpEnvelope);

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
