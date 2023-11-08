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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\AmqpCompat\Logger;

use Asmblah\PhpAmqpCompat\Logger\Logger;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Mockery\MockInterface;
use PhpAmqpLib\Exception\AMQPIOException;
use Psr\Log\LoggerInterface as PsrLoggerInterface;
use Psr\Log\LogLevel;

/**
 * Class LoggerTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class LoggerTest extends AbstractTestCase
{
    private Logger $logger;
    private MockInterface&PsrLoggerInterface $wrappedLogger;

    public function setUp(): void
    {
        $this->wrappedLogger = mock(PsrLoggerInterface::class);

        $this->logger = new Logger($this->wrappedLogger);
    }

    public function testGetWrappedLoggerReturnsTheWrappedLogger(): void
    {
        static::assertSame($this->wrappedLogger, $this->logger->getWrappedLogger());
    }

    public function testLogLogsCorrectly(): void
    {
        $this->wrappedLogger->expects()
            ->log(
                LogLevel::EMERGENCY,
                'My log message',
                ['my' => 'context']
            )
            ->once();

        $this->logger->log(
            LogLevel::EMERGENCY,
            'My log message',
            ['my' => 'context']
        );
    }

    public function testLogAmqplibExceptionLogsCorrectlyWithDefaultMessage(): void
    {
        $this->wrappedLogger->expects()
            ->critical(
                'MyClass::myMethod(): Amqplib failure',
                [
                    'exception' => AMQPIOException::class,
                    'message' => 'Bang!',
                    'code' => 123,
                ]
            )
            ->once();

        $this->logger->logAmqplibException('MyClass::myMethod', new AMQPIOException('Bang!', 123));
    }

    public function testLogAmqplibExceptionLogsCorrectlyWithCustomMessage(): void
    {
        $this->wrappedLogger->expects()
            ->critical(
                'MyClass::myMethod(): My custom message',
                [
                    'exception' => AMQPIOException::class,
                    'message' => 'Boom!',
                    'code' => 456,
                ]
            )
            ->once();

        $this->logger->logAmqplibException(
            'MyClass::myMethod',
            new AMQPIOException('Boom!', 456),
            'My custom message'
        );
    }
}
