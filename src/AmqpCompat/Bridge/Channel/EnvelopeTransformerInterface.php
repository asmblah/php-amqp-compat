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
use PhpAmqpLib\Message\AMQPMessage as AmqplibMessage;

/**
 * Interface EnvelopeTransformerInterface.
 *
 * Transforms php-amqplib Message objects into AMQPEnvelope instances.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface EnvelopeTransformerInterface
{
    /**
     * Transforms the given php-amqplib Message into an AMQPEnvelope.
     */
    public function transformMessage(AmqplibMessage $message): AMQPEnvelope;
}
