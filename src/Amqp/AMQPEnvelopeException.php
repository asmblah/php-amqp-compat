<?php

/*
 * PHP AMQP-Compat - php-amqp/ext-amqp compatibility.
 * Copyright (c) Dan Phillimore (asmblah)
 * https://github.com/asmblah/php-amqp-compat/
 *
 * Released under the MIT license.
 * https://github.com/asmblah/php-amqp-compat/raw/master/MIT-LICENSE.txt
 */

declare(strict_types=1);

/**
 * Class AMQPEnvelopeException.
 *
 * Emulates AMQPEnvelopeException from pecl-amqp.
 *
 * @see {@link https://github.com/php-amqp/php-amqp/blob/v1.11.0/stubs/AMQPEnvelopeException.php}
 */
class AMQPEnvelopeException extends AMQPException
{
    /**
     * @var AMQPEnvelope
     */
    public $envelope;
}
