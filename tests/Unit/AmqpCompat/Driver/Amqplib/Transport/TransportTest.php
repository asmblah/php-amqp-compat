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

use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transport\Transport;
use Asmblah\PhpAmqpCompat\Exception\SocketConfigurationFailedException;
use Asmblah\PhpAmqpCompat\Exception\TransportConfigurationFailedException;
use Asmblah\PhpAmqpCompat\Socket\SocketSubsystemInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Mockery;
use Mockery\MockInterface;
use PhpAmqpLib\Connection\AbstractConnection as AmqplibConnection;
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

        $this->socketSubsystem = mock(SocketSubsystemInterface::class);

        $this->streamIO = mock(StreamIO::class, [
            'getSocket' => $this->clientSocketStream,
        ]);
        $this->amqplibConnection = mock(AmqplibConnection::class, [
            'getIO' => $this->streamIO,
        ]);

        $this->transport = new Transport($this->amqplibConnection, $this->socketSubsystem);
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

    public function testGetAmqplibConnectionFetchesAmqplibConnection(): void
    {
        static::assertSame($this->amqplibConnection, $this->transport->getAmqplibConnection());
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
}
