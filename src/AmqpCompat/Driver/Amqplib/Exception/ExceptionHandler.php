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
use AMQPExchange;
use AMQPExchangeException;
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
    public function handleExchangeException(Exception $exception, AMQPExchange $exchange, string $methodName): void
    {
        if (!$exception instanceof AMQPExceptionInterface) {
            throw new InvalidArgumentException(sprintf(
                'Expected an instance of "%s" but got "%s"',
                AMQPExceptionInterface::class,
                $exception::class
            ));
        }

        // Log details of the internal php-amqplib exception,
        // that cannot be included in the php-amqp/ext-amqp -compatible exception.
        $this->logger->logAmqplibException($methodName, $exception);

        if ($exception instanceof AMQPProtocolException) {
            throw new AMQPExchangeException(
                sprintf(
                    'Server channel error: %d, message: %s',
                    $exception->getCode(),
                    $exception->getMessage()
                ),
                $exception->getCode(),
                $exception
            );
        }

        $libraryMessage = $exception->getMessage();

        throw new AMQPConnectionException(
            sprintf(
                'Server connection error: %d, message: %s',
                $exception->getCode(),
                preg_replace('/\(\d+, \d+\)$/', '', $libraryMessage)
            ),
            $exception->getCode(),
            $exception
        );
    }
}
