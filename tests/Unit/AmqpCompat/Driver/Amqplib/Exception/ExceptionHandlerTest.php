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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\AmqpCompat\Driver\Amqplib\Exception;

use AMQPConnectionException;
use AMQPExchange;
use AMQPExchangeException;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Exception\ExceptionHandler;
use Asmblah\PhpAmqpCompat\Logger\LoggerInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use InvalidArgumentException;
use Mockery\MockInterface;
use PhpAmqpLib\Exception\AMQPIOException;
use PhpAmqpLib\Exception\AMQPLogicException;
use PhpAmqpLib\Exception\AMQPProtocolException;
use RuntimeException;

/**
 * Class ExceptionHandlerTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ExceptionHandlerTest extends AbstractTestCase
{
    private MockInterface&AMQPExchange $amqpExchange;
    private ExceptionHandler $handler;
    private MockInterface&LoggerInterface $logger;

    public function setUp(): void
    {
        $this->amqpExchange = mock(AMQPExchange::class);
        $this->logger = mock(LoggerInterface::class, [
            'logAmqplibException' => null,
        ]);

        $this->handler = new ExceptionHandler($this->logger);
    }

    public function testHandleExchangeExceptionRaisesExceptionWhenNonAmqplibExceptionIsGiven(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Expected an instance of "PhpAmqpLib\Exception\AMQPExceptionInterface" but got "RuntimeException"'
        );

        $this->handler->handleExchangeException(new RuntimeException('Bang!'), $this->amqpExchange, 'myMethod');
    }

    public function testHandleExchangeExceptionLogsAmqplibExceptionViaLogger(): void
    {
        $exception = new AMQPLogicException('Bang!');

        $this->logger->expects()
            ->logAmqplibException('myMethod', $exception)
            ->once();

        try {
            $this->handler->handleExchangeException($exception, $this->amqpExchange, 'myMethod');
        } catch (AMQPConnectionException) {}
    }

    public function testHandleExchangeExceptionRaisesExchangeExceptionOnAmqpProtocolException(): void
    {
        $exception = new AMQPProtocolException(21, 'my reply text', [21, 23]);

        $this->expectException(AMQPExchangeException::class);
        $this->expectExceptionMessage('Server channel error: 21, message: my reply text');

        $this->handler->handleExchangeException($exception, $this->amqpExchange, 'myMethod');
    }

    public function testHandleExchangeExceptionRaisesTrimmedConnectionExceptionOnOtherAmqpException(): void
    {
        $exception = new AMQPIOException('my message that ends in extra data(21, 45)');

        $this->expectException(AMQPConnectionException::class);
        $this->expectExceptionMessageMatches(
            '/Server connection error: 0, message: my message that ends in extra data$/'
        );

        $this->handler->handleExchangeException($exception, $this->amqpExchange, 'myMethod');
    }
}
