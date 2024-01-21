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

namespace Asmblah\PhpAmqpCompat\Driver\Amqplib\Transformer;

use Asmblah\PhpAmqpCompat\Driver\Common\Processor\ValueProcessorInterface;
use PhpAmqpLib\Message\AMQPMessage as AmqplibMessage;
use PhpAmqpLib\Wire\AMQPTable as AmqplibTable;

/**
 * Class MessageTransformer.
 *
 * Transforms AMQP envelope data into php-amqplib Message objects.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class MessageTransformer implements MessageTransformerInterface
{
    public function __construct(
        private readonly ValueProcessorInterface $valueProcessor
    ) {
    }

    /**
     * @inheritDoc
     */
    public function transformEnvelope(
        string $message,
        array $attributes
    ): AmqplibMessage {
        // Strip any empty attributes.
        $attributes = array_filter($attributes, static fn ($value) => $value !== '');

        // Note that this must be first so that headers are processed before the AMQP table is built below.
        $attributes = $this->valueProcessor->processValueForDriver($attributes);

        if (array_key_exists('headers', $attributes)) {
            // Amqplib expects "application_headers" instead.
            $attributes['application_headers'] = new AmqplibTable($attributes['headers']);

            unset($attributes['headers']);
        }

        if (!array_key_exists('content_type', $attributes)) {
            // Default content type is text/plain.
            $attributes['content_type'] = 'text/plain';
        }

        return new AmqplibMessage($message, $attributes);
    }
}
