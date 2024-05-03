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

use PhpAmqpLib\Message\AMQPMessage as AmqplibMessage;

/**
 * Interface MessageTransformerInterface.
 *
 * Transforms AMQP envelope data into php-amqplib Message objects.
 *
 * @phpstan-type EnvelopeAttributes array{
 *                                       app_id?: string,
 *                                       content_encoding?: string,
 *                                       content_type?: string,
 *                                       delivery_mode?: string,
 *                                       expiration?: string,
 *                                       headers?: array<mixed>,
 *                                       message_id?: string,
 *                                       priority?: string,
 *                                       reply_to?: string,
 *                                       timestamp?: string,
 *                                       type?: string,
 *                                       user_id?: string,
 *                                      }
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
