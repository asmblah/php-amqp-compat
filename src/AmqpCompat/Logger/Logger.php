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

namespace Asmblah\PhpAmqpCompat\Logger;

use PhpAmqpLib\Exception\AMQPExceptionInterface;
use Psr\Log\AbstractLogger as PsrAbstractLogger;
use Psr\Log\LoggerInterface as PsrLoggerInterface;
use Stringable;

/**
 * Class Logger.
 *
 * Decorator for the configured PSR logger that allows for common log logic to be abstracted.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class Logger extends PsrAbstractLogger implements LoggerInterface
{
    public function __construct(
        private readonly PsrLoggerInterface $wrappedLogger
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getWrappedLogger(): PsrLoggerInterface
    {
        return $this->wrappedLogger;
    }

    /**
     * @inheritDoc
     */
    public function log($level, Stringable|string $message, array $context = []): void
    {
        $this->wrappedLogger->log($level, $message, $context);
    }

    /**
     * @inheritDoc
     */
    public function logAmqplibException(string $methodName, AMQPExceptionInterface $exception): void
    {
        $this->wrappedLogger->error($methodName . '(): Amqplib failure', [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
        ]);
    }
}
