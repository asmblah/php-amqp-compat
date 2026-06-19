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
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transformer\EnvelopeTransformerInterface;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transformer\MessageTransformerInterface;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transport\Transport;
use Asmblah\PhpAmqpCompat\Driver\Common\Channel\ChannelInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Exception\ExceptionHandlerInterface;
use Asmblah\PhpAmqpCompat\Exception\HeartbeatMissedException;
use Asmblah\PhpAmqpCompat\Exception\SocketConfigurationFailedException;
use Asmblah\PhpAmqpCompat\Exception\TransportConfigurationFailedException;
use Asmblah\PhpAmqpCompat\Misc\ClockInterface;
use Asmblah\PhpAmqpCompat\Socket\SocketSubsystemInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Mockery;
use Mockery\MockInterface;
use PhpAmqpLib\Channel\AMQPChannel as AmqplibChannel;
use PhpAmqpLib\Connection\AbstractConnection as AmqplibConnection;
use PhpAmqpLib\Exception\AMQPHeartbeatMissedException;
use PhpAmqpLib\Exception\AMQPLogicException;
use PhpAmqpLib\Wire\IO\AbstractIO;
use PhpAmqpLib\Wire\IO\StreamIO;
use ReflectionClass;
use RuntimeException;
use Socket;

/**
 * Class TransportTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class TransportTest extends AbstractTestCase
{
    private MockInterface&AmqplibConnection $amqplibConnection;
    /**
     * @var resource|null
     */
    private $clientSocketStream;
    private MockInterface&ClockInterface $clock;
    private MockInterface&EnvelopeTransformerInterface $envelopeTransformer;
    private MockInterface&ExceptionHandlerInterface $exceptionHandler;
    private MockInterface&MessageTransformerInterface $messageTransformer;
    /**
     * @var resource|null
     */
    private $serverSocketStream;
    private MockInterface&SocketSubsystemInterface $socketSubsystem;
    private MockInterface&StreamIO $streamIO;
    private Transport $transport;

    public function setUp(): void
    {
        $this->serverSocketStream = stream_socket_server(
            'tcp://127.0.0.1:0',
            $errorCode,
            $errorMessage
        );

        if (!$this->serverSocketStream) {
            $this->fail("Failed to create server socket: $errorMessage ($errorCode)");
        }

        $serverName = stream_socket_get_name($this->serverSocketStream, remote: false);
        $this->clientSocketStream = stream_socket_client($serverName, $errorCode, $errorMessage);

        if (!$this->clientSocketStream) {
            fclose($this->serverSocketStream);

            $this->fail("Failed to create client socket: $errorMessage ($errorCode)");
        }

        $this->clock = mock(ClockInterface::class);
        $this->envelopeTransformer = mock(EnvelopeTransformerInterface::class);
        $this->exceptionHandler = mock(ExceptionHandlerInterface::class);
        $this->messageTransformer = mock(MessageTransformerInterface::class);
        $this->socketSubsystem = mock(SocketSubsystemInterface::class);

        $this->streamIO = mock(StreamIO::class, [
            'getSocket' => $this->clientSocketStream,
        ]);
        $this->amqplibConnection = mock(AmqplibConnection::class, [
            'getIO' => $this->streamIO,
        ]);

        $this->transport = new Transport(
            $this->amqplibConnection,
            $this->socketSubsystem,
            $this->clock,
            $this->exceptionHandler,
            $this->envelopeTransformer,
            $this->messageTransformer
        );
    }

    public function tearDown(): void
    {
        if ($this->clientSocketStream) {
            fclose($this->clientSocketStream);
        }

        if ($this->serverSocketStream) {
            fclose($this->serverSocketStream);
        }
    }

    public function testCheckHeartbeatDoesNothingWhenWithinInterval(): void
    {
        $this->amqplibConnection->allows('getLastActivity')->andReturn(1000);
        $this->amqplibConnection->allows('getHeartbeat')->andReturn(60);
        $this->clock->allows('getUnixTimestamp')->andReturn(1010); // Within 30-second interval.

        $this->amqplibConnection->expects('checkHeartBeat')
            ->never();

        $this->transport->checkHeartbeat();
    }

    public function testCheckHeartbeatChecksHeartbeatWhenIntervalExceeded(): void
    {
        $this->amqplibConnection->allows('getLastActivity')->andReturn(1000);
        $this->amqplibConnection->allows('getHeartbeat')->andReturn(60);
        $this->clock->allows('getUnixTimestamp')->andReturn(1100); // Past 30-second interval.

        $this->amqplibConnection->expects('checkHeartBeat')
            ->once();

        $this->transport->checkHeartbeat();
    }

    public function testCheckHeartbeatThrowsHeartbeatMissedExceptionWhenAmqplibThrows(): void
    {
        $this->amqplibConnection->allows('getLastActivity')->andReturn(1000);
        $this->amqplibConnection->allows('getHeartbeat')->andReturn(60);
        $this->clock->allows('getUnixTimestamp')->andReturn(1100);
        $this->amqplibConnection->allows('checkHeartBeat')
            ->andThrow(new AMQPHeartbeatMissedException('Heartbeat missed!'));

        $this->expectException(HeartbeatMissedException::class);
        $this->expectExceptionMessage('Heartbeat missed: Heartbeat missed!');

        $this->transport->checkHeartbeat();
    }

    public function testDisconnectClosesAmqplibConnection(): void
    {
        $this->amqplibConnection->expects('close')
            ->once();

        $this->transport->disconnect(AMQPConnectionException::class, 'AMQPConnection::disconnect');
    }

    public function testDisconnectPassesExceptionToHandlerOnAmqplibFailure(): void
    {
        $exception = new AMQPLogicException('Bang!');
        $this->amqplibConnection->allows('close')->andThrow($exception);

        $this->exceptionHandler->expects()
            ->handleException($exception, AMQPConnectionException::class, 'AMQPConnection::disconnect')
            ->once()
            ->andThrow(new AMQPConnectionException('Bang!'));

        $this->expectException(AMQPConnectionException::class);
        $this->expectExceptionMessage('Bang!');

        $this->transport->disconnect(AMQPConnectionException::class, 'AMQPConnection::disconnect');
    }

    public function testGetAmqplibConnectionFetchesAmqplibConnection(): void
    {
        static::assertSame($this->amqplibConnection, $this->transport->getAmqplibConnection());
    }

    public function testGetHeartbeatIntervalReturnsHalfTheHeartbeatTimeout(): void
    {
        $this->amqplibConnection->allows('getHeartbeat')
            ->andReturn(60);

        static::assertSame(30, $this->transport->getHeartbeatInterval());
    }

    public function testGetHeartbeatIntervalRoundsUpWhenNeeded(): void
    {
        $this->amqplibConnection->allows('getHeartbeat')
            ->andReturn(11);

        static::assertSame(6, $this->transport->getHeartbeatInterval());
    }

    /**
     * @dataProvider booleanDataProvider
     */
    public function testIsBusyDelegatesToAmqplibConnection(bool $writing): void
    {
        $this->amqplibConnection->allows('isWriting')
            ->andReturn($writing);

        static::assertSame($writing, $this->transport->isBusy());
    }

    /**
     * @dataProvider booleanDataProvider
     */
    public function testIsConnectedDelegatesToAmqplibConnection(bool $connected): void
    {
        $this->amqplibConnection->allows('isConnected')
            ->andReturn($connected);

        static::assertSame($connected, $this->transport->isConnected());
    }

    public function testOpenChannelOpensAmqplibChannelAndReturnsChannelWrapper(): void
    {
        $amqplibChannel = mock(AmqplibChannel::class);

        $this->amqplibConnection->expects('channel')
            ->once()
            ->andReturn($amqplibChannel);

        $result = $this->transport->openChannel(AMQPConnectionException::class, 'AMQPConnection::__construct');

        static::assertInstanceOf(ChannelInterface::class, $result);
    }

    public function testOpenChannelPassesExceptionToHandlerOnAmqplibFailure(): void
    {
        $exception = new AMQPLogicException('Bang!');
        $this->amqplibConnection->allows('channel')->andThrow($exception);

        $this->exceptionHandler->expects()
            ->handleException($exception, AMQPConnectionException::class, 'AMQPChannel::__construct')
            ->once()
            ->andThrow(new AMQPConnectionException('Bang!'));

        $this->expectException(AMQPConnectionException::class);
        $this->expectExceptionMessage('Bang!');

        $this->transport->openChannel(AMQPConnectionException::class, 'AMQPChannel::__construct');
    }

    public function testSetReadTimeoutCallsSocketSubsystemWhenStreamIOIsValid(): void
    {
        $timeout = 5.5;

        $this->socketSubsystem->expects('setSocketReadTimeout')
            ->with(
                Mockery::on(fn ($socket) => socket_export_stream($socket) === $this->clientSocketStream),
                $timeout
            )
            ->once();

        $this->transport->setReadTimeout($timeout);
    }

    public function testSetReadTimeoutThrowsRuntimeExceptionWhenIOIsNotStreamIO(): void
    {
        $nonStreamIO = mock(AbstractIO::class);
        $this->amqplibConnection->allows('getIO')
            ->andReturn($nonStreamIO);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only StreamIO is supported');

        $this->transport->setReadTimeout(5.0);
    }

    public function testSetReadTimeoutRaisesExceptionWhenSocketImportFails(): void
    {
        $nonSocketStream = fopen('php://memory', 'rb+');
        $this->streamIO->allows('getSocket')
            ->andReturn($nonSocketStream);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed importing socket from stream');

        // Suppress the expected warning raised by socket_import_stream() to avoid polluting PHPUnit results.
        @$this->transport->setReadTimeout(5.0);
    }

    public function testSetReadTimeoutThrowsTransportConfigurationFailedExceptionWhenSocketConfigurationFails(): void
    {
        $socketException = new SocketConfigurationFailedException('Socket error');
        $this->socketSubsystem->allows('setSocketReadTimeout')
            ->andThrow($socketException);

        $this->expectException(TransportConfigurationFailedException::class);
        $this->expectExceptionMessage('Could not set socket read timeout');

        $this->transport->setReadTimeout(3.0);
    }

    /**
     * @dataProvider timeoutValueDataProvider
     */
    public function testSetReadTimeoutHandlesVariousTimeoutValues(float $timeout): void
    {
        $this->streamIO->allows('getSocket')
            ->andReturn($this->clientSocketStream);

        $this->socketSubsystem->expects('setSocketReadTimeout')
            ->with(Mockery::type(Socket::class), $timeout)
            ->once();

        $this->transport->setReadTimeout($timeout);
    }

    /**
     * @return array<mixed>
     */
    public static function timeoutValueDataProvider(): array
    {
        return [
            'zero timeout' => [0.0],
            'small timeout' => [0.1],
            'integer timeout (as float)' => [5.0],
            'large timeout' => [3600.0],
            'fractional timeout' => [2.75],
        ];
    }

    public function testSetReadTimeoutSetsProtectedReadTimeoutPropertyViaBoundClosure(): void
    {
        $timeout = 7.5;
        $this->socketSubsystem->allows('setSocketReadTimeout');
        // Use reflection to access the protected $read_timeout property.
        $reflection = new ReflectionClass(StreamIO::class);
        $readTimeoutProperty = $reflection->getProperty('read_timeout');

        // Verify the property is initially not set to our test value.
        static::assertNotSame($timeout, $readTimeoutProperty->getValue($this->streamIO));
        $this->transport->setReadTimeout($timeout);
        // Verify the bound closure correctly set the protected property.
        static::assertSame($timeout, $readTimeoutProperty->getValue($this->streamIO));
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function booleanDataProvider(): array
    {
        return [
            'true' => [true],
            'false' => [false],
        ];
    }
}
