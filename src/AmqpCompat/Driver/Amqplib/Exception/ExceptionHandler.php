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

namespace Asmblah\PhpAmqpCompat\Driver\Amqplib\Exception;

use AMQPConnectionException;
use AMQPException;
use Asmblah\PhpAmqpCompat\Driver\Common\Exception\ExceptionHandlerInterface;
use Asmblah\PhpAmqpCompat\Logger\LoggerInterface;
use Exception;
use InvalidArgumentException;
use PhpAmqpLib\Exception\AMQPExceptionInterface;
use PhpAmqpLib\Exception\AMQPProtocolException;

/**
 * Class ExceptionHandler.
 *
 * Handles exceptions from the underlying AMQP library.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ExceptionHandler implements ExceptionHandlerInterface
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function handleException(Exception $libraryException, string $exceptionClass, string $methodName): never
    {
        if (!$libraryException instanceof AMQPExceptionInterface) {
            throw new InvalidArgumentException(sprintf(
                'Expected an instance of "%s" but got "%s"',
                AMQPExceptionInterface::class,
                $libraryException::class
            ));
        }

        if (!is_subclass_of($exceptionClass, AMQPException::class)) {
            throw new InvalidArgumentException(sprintf(
                'Expected a class that extends "%s" but got "%s"',
                AMQPException::class,
                $exceptionClass
            ));
        }

        // Log details of the internal php-amqplib exception,
        // that cannot be included in the php-amqp/ext-amqp -compatible exception.
        $this->logger->logAmqplibException($methodName, $libraryException);

        if ($libraryException instanceof AMQPProtocolException) {
            throw new $exceptionClass(
                sprintf(
                    'Server channel error: %d, message: %s',
                    $libraryException->getCode(),
                    $libraryException->getMessage()
                ),
                $libraryException->getCode(),
                $libraryException
            );
        }

        $libraryMessage = $libraryException->getMessage();

        throw new AMQPConnectionException(
            sprintf(
                'Server connection error: %d, message: %s',
                $libraryException->getCode(),
                preg_replace('/\(\d+, \d+\)$/', '', $libraryMessage)
            ),
            $libraryException->getCode(),
            $libraryException
        );
    }
}
