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

namespace Asmblah\PhpAmqpCompat\Bridge;

use Asmblah\PhpAmqpCompat\Bridge\Channel\EnvelopeTransformerInterface;
use Asmblah\PhpAmqpCompat\Error\ErrorReporterInterface;
use Asmblah\PhpAmqpCompat\Logger\LoggerInterface;

/**
 * Interface AmqpBridgeResourceInterface.
 *
 * Defines the interface that all bridge resources should expose for portability.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface AmqpBridgeResourceInterface
{
    /**
     * Fetches the AMQPEnvelope transformer.
     */
    public function getEnvelopeTransformer(): EnvelopeTransformerInterface;

    /**
     * Fetches the ErrorReporter.
     */
    public function getErrorReporter(): ErrorReporterInterface;

    /**
     * Fetches the logger.
     */
    public function getLogger(): LoggerInterface;
}
