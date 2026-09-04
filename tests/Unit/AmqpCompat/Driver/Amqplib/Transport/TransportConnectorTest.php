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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\AmqpCompat\Driver\Amqplib\Transport;

use AMQPConnectionException;
use Asmblah\PhpAmqpCompat\Connection\Config\ConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Connection\ConnectorInterface;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Logger\LoggerInterface;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transformer\EnvelopeTransformerInterface;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transformer\MessageTransformerInterface;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transport\Transport;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transport\TransportConnector;
use Asmblah\PhpAmqpCompat\Driver\Common\Exception\ExceptionHandlerInterface;
use Asmblah\PhpAmqpCompat\Misc\ClockInterface;
use Asmblah\PhpAmqpCompat\Socket\SocketSubsystemInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Mockery\MockInterface;
use PhpAmqpLib\Connection\AbstractConnection as AmqplibConnection;
use PhpAmqpLib\Exception\AMQPIOException;
use PhpAmqpLib\Exception\AMQPLogicException;

/**
 * Class TransportConnectorTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class TransportConnectorTest extends AbstractTestCase
{
    private MockInterface&AmqplibConnection $amqplibConnection;
    private MockInterface&ClockInterface $clock;
    private MockInterface&ConnectionConfigInterface $connectionConfig;
    private MockInterface&ConnectorInterface $connector;
    private MockInterface&EnvelopeTransformerInterface $envelopeTransformer;
    private MockInterface&ExceptionHandlerInterface $exceptionHandler;
    private MockInterface&LoggerInterface $logger;
    private MockInterface&MessageTransformerInterface $messageTransformer;
    private MockInterface&SocketSubsystemInterface $socketSubsystem;
    private TransportConnector $transportConnector;

    public function setUp(): void
    {
        $this->amqplibConnection = mock(AmqplibConnection::class);
        $this->clock = mock(ClockInterface::class);
        $this->connectionConfig = mock(ConnectionConfigInterface::class);
        $this->connector = mock(ConnectorInterface::class);
        $this->envelopeTransformer = mock(EnvelopeTransformerInterface::class);
        $this->exceptionHandler = mock(ExceptionHandlerInterface::class);
        $this->logger = mock(LoggerInterface::class);
        $this->messageTransformer = mock(MessageTransformerInterface::class);
        $this->socketSubsystem = mock(SocketSubsystemInterface::class);

        $this->transportConnector = new TransportConnector(
            $this->connector,
            $this->socketSubsystem,
            $this->clock,
            $this->exceptionHandler,
            $this->logger,
            $this->envelopeTransformer,
            $this->messageTransformer
        );
    }

    public function testConnectCallsConnector(): void
    {
        $this->connector->expects()
            ->connect($this->connectionConfig)
            ->once()
            ->andReturn($this->amqplibConnection);

        $this->transportConnector->connect($this->connectionConfig, 'SomeClass::someMethod');
    }

    public function testConnectReturnsCorrectlyCreatedTransport(): void
    {
        $this->connector->allows()
            ->connect($this->connectionConfig)
            ->andReturn($this->amqplibConnection);

        /** @var Transport $transport */
        $transport = $this->transportConnector->connect($this->connectionConfig, 'SomeClass::someMethod');

        static::assertInstanceOf(Transport::class, $transport);
        static::assertSame($this->amqplibConnection, $transport->getAmqplibConnection());
    }

    public function testConnectThrowsSocketErrorExceptionWhenAmqplibThrowsIoException(): void
    {
        $this->connector->allows()
            ->connect($this->connectionConfig)
            ->andThrow(new AMQPIOException('Bang!'));
        $this->logger->allows('logAmqplibException');

        $this->expectException(AMQPConnectionException::class);
        $this->expectExceptionMessage('Socket error: could not connect to host.');

        $this->transportConnector->connect($this->connectionConfig, 'SomeClass::someMethod');
    }

    public function testConnectThrowsLoginFailureExceptionWhenAmqplibThrowsOtherException(): void
    {
        $this->connector->allows()
            ->connect($this->connectionConfig)
            ->andThrow(new AMQPLogicException('Bang!'));
        $this->logger->allows('logAmqplibException');

        $this->expectException(AMQPConnectionException::class);
        $this->expectExceptionMessage('Library error: connection closed unexpectedly - Potential login failure.');

        $this->transportConnector->connect($this->connectionConfig, 'SomeClass::someMethod');
    }

    public function testConnectLogsAmqplibExceptionDetails(): void
    {
        $exception = new AMQPIOException('Bang!');
        $this->connector->allows()
            ->connect($this->connectionConfig)
            ->andThrow($exception);

        $this->logger->expects()
            ->logAmqplibException('SomeClass::someMethod', $exception)
            ->once();

        try {
            $this->transportConnector->connect($this->connectionConfig, 'SomeClass::someMethod');
        } catch (AMQPConnectionException) {
        }
    }
}
