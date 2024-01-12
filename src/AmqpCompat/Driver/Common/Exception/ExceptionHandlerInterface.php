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

use AMQPException;
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
     * Handles the given exception for an AMQP operation, usually by raising a different exception.
     *
     * @param class-string<AMQPException> $exceptionClass
     */
    public function handleException(Exception $libraryException, string $exceptionClass, string $methodName): never;
}
