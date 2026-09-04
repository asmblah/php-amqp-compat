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

namespace Asmblah\PhpAmqpCompat\Driver\Amqplib\Logger;

use Asmblah\PhpAmqpCompat\Driver\Common\Logger\LoggerInterface as AmqpCompatLoggerInterface;
use PhpAmqpLib\Exception\AMQPExceptionInterface;
use Throwable;

/**
 * Interface LoggerInterface.
 *
 * Extension to the PSR logger spec that allows for common log logic to be abstracted.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface LoggerInterface extends AmqpCompatLoggerInterface
{
    /**
     * Logs details of the php-amqplib exception.
     */
    public function logAmqplibException(
        string $methodName,
        AMQPExceptionInterface&Throwable $exception,
        string $message = 'Amqplib failure'
    ): void;
}
