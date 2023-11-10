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

namespace Asmblah\PhpAmqpCompat\Driver\Common\Exception;

use AMQPExchange;
use Exception;

/**
 * Interface ExceptionHandlerInterface.
 *
 * Handles exceptions from the underlying AMQP library.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface ExceptionHandlerInterface
{
    /**
     * Handles the given exception for an AMQPExchange, usually by raising a different exception.
     */
    public function handleExchangeException(Exception $exception, AMQPExchange $exchange, string $methodName): void;
}
