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
use Asmblah\PhpAmqpCompat\Driver\Common\Processor\ValueProcessorInterface;
use LogicException;
use PhpAmqpLib\Message\AMQPMessage as AmqplibMessage;
use PhpAmqpLib\Wire\AMQPTable as AmqplibTable;

/**
 * Class EnvelopeTransformer.
 *
 * Transforms php-amqplib Message objects into AMQPEnvelope instances.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class EnvelopeTransformer implements EnvelopeTransformerInterface
{
    public function __construct(
        private readonly ValueProcessorInterface $valueProcessor
    ) {
    }

    /**
     * @inheritDoc
     */
    public function transformMessage(AmqplibMessage $message): AMQPEnvelope
    {
        $properties = $message->get_properties();

        $applicationHeadersTable = $properties['application_headers'] ?? null;

        if ($applicationHeadersTable === null) {
            $headers = [];
        } elseif ($applicationHeadersTable instanceof AmqplibTable) {
            $headers = $applicationHeadersTable->getNativeData();
        } else {
            throw new LogicException(__METHOD__ . '() :: application_headers is not an AMQPTable');
        }

        $headers = $this->valueProcessor->processValueFromDriver($headers);

        /** @var ?string $contentEncoding */
        $contentEncoding = $message->getContentEncoding();

        if ($contentEncoding === '' || $contentEncoding === null) {
            // Content encoding is handled a bit strangely by php-amqplib, potentially stored in two places.
            $contentEncoding = $properties['content_encoding'] ?? '';
        }

        return new AMQPEnvelope(
            $message->getBody(),
            $message->getConsumerTag() ?? '',
            $message->getDeliveryTag(),
            $message->getExchange(),
            $message->isRedelivered(),
            $message->getRoutingKey(),
            $properties['content_type'] ?? null,
            $contentEncoding,
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
    }
}
