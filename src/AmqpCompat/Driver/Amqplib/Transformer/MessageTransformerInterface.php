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

use Asmblah\PhpAmqpCompat\Bridge\Channel\Envelope;
use PhpAmqpLib\Message\AMQPMessage as AmqplibMessage;

/**
 * Interface MessageTransformerInterface.
 *
 * Transforms AMQP envelope data into php-amqplib Message objects.
 *
 * @phpstan-import-type EnvelopeAttributes from Envelope
 * @author Dan Phillimore <dan@ovms.co>
 */
interface MessageTransformerInterface
{
    /**
     * Transforms the given envelope data into an php-amqplib Message.
     *
     * @param string $message The message body.
     * @param EnvelopeAttributes $attributes
     */
    public function transformEnvelope(
        string $message,
        array $attributes
    ): AmqplibMessage;
}
