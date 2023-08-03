<?php

/*
 * PHP AMQP-Compat - php-amqp/ext-amqp compatibility.
 * Copyright (c) Dan Phillimore (asmblah)
 * https://github.com/asmblah/php-amqp-compat/
 *
 * Released under the MIT license.
 * https://github.com/asmblah/php-amqp-compat/raw/master/MIT-LICENSE.txt
 */

declare(strict_types=1);

namespace Asmblah\PhpAmqpCompat\Tests\Unit\Amqp;

use AMQPConnection;
use AMQPConnectionException;
use Asmblah\PhpAmqpCompat\AmqpManager;
use Asmblah\PhpAmqpCompat\Bridge\AmqpBridge;
use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Connection\Config\ConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Integration\AmqpIntegrationInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Mockery\MockInterface;
use PhpAmqpLib\Connection\AbstractConnection as AmqplibConnection;
use PhpAmqpLib\Exception\AMQPIOException;
use Psr\Log\LoggerInterface;

/**
 * Class AMQPConnectionTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AMQPConnectionTest extends AbstractTestCase
{
    private ?AMQPConnection $amqpConnection;
    /**
     * @var (MockInterface&AmqpIntegrationInterface)|null
     */
    private $amqpIntegration;
    /**
     * @var (MockInterface&AmqplibConnection)|null
     */
    private $amqplibConnection;
    /**
     * @var (MockInterface&AmqpConnectionBridgeInterface)|null
     */
    private $connectionBridge;
    /**
     * @var (MockInterface&ConnectionConfigInterface)|null
     */
    private $connectionConfig;
    /**
     * @var (MockInterface&LoggerInterface)|null
     */
    private $logger;

    public function setUp(): void
    {
        $this->amqplibConnection = mock(AmqplibConnection::class);
        $this->connectionBridge = mock(AmqpConnectionBridgeInterface::class, [
            'getAmqplibConnection' => $this->amqplibConnection,
        ]);
        $this->connectionConfig = mock(ConnectionConfigInterface::class, [
            'getConnectionName' => 'my-connection-name',
            'getConnectionTimeout' => 0,
            'getHeartbeatInterval' => 123,
            'getHost' => 'my.host',
            'getPassword' => 'mypa55w0rd',
            'getPort' => 4321,
            'getReadTimeout' => 12.34,
            'getRpcTimeout' => 56.78,
            'getUser' => 'myuser',
            'getVirtualHost' => '/my/vhost',
            'getWriteTimeout' => 9.1,
            'toLoggableArray' => ['my' => 'loggable connection config'],
        ]);
        $this->logger = mock(LoggerInterface::class, [
            'debug' => null,
            'error' => null,
        ]);
        $this->amqpIntegration = mock(AmqpIntegrationInterface::class, [
            'connect' => $this->connectionBridge,
            'createConnectionConfig' => $this->connectionConfig,
            'getLogger' => $this->logger,
        ]);
        AmqpManager::setAmqpIntegration($this->amqpIntegration);

        $this->amqpConnection = new AMQPConnection();
    }

    public function tearDown(): void
    {
        AmqpManager::setAmqpIntegration(null);
        AmqpManager::setConfiguration(null);
    }

    public function testConstructorPassesCredentialsWhenLoadingConfiguration(): void
    {
        $this->amqpIntegration->expects()
            ->createConnectionConfig(['my' => 'config'])
            ->once()
            ->andReturn($this->connectionConfig);

        new AMQPConnection(['my' => 'config']);
    }

    public function testConstructorCorrectlyBridgesTheConnectionToTheCreatedConnectionConfig(): void
    {
        static::assertSame($this->connectionConfig, AmqpBridge::getConnectionConfig($this->amqpConnection));
    }

    public function testConstructorLogsConnectionConfigAsDebugLog(): void
    {
        $this->logger->expects()
            ->debug('AMQPConnection::__construct() connection created (not yet opened)', [
                'config' => ['my' => 'loggable connection config'],
            ])
            ->once();

        new AMQPConnection(['my' => 'config']);
    }

    public function testConstructorThrowsWhenConnectionTimeoutIsSpecifiedAsNegative(): void
    {
        $this->connectionConfig->allows()
            ->getConnectionTimeout()
            ->andReturn(-2);

        $this->expectException(AMQPConnectionException::class);
        $this->expectExceptionMessage('Parameter \'connect_timeout\' must be greater than or equal to zero.');

        new AMQPConnection(['connect_timeout' => -2]);
    }

    public function testConnectReturnsTrueWithoutReconnectingWhenAlreadyConnected(): void
    {
        $this->amqpConnection->connect();

        $this->amqpIntegration->expects()
            ->connect($this->connectionConfig)
            ->never();

        $this->amqpConnection->connect(); // Connect for a second time.
    }

    public function testConnectCorrectlyBridgesTheConnectionToTheCreatedConnectionBridge(): void
    {
        $this->amqpIntegration->expects()
            ->connect($this->connectionConfig)
            ->once()
            ->andReturn($this->connectionBridge);

        static::assertTrue($this->amqpConnection->connect());
        static::assertSame($this->connectionBridge, AmqpBridge::getBridgeConnection($this->amqpConnection));
    }

    public function testConnectLogsConnectionConfigAsDebugLog(): void
    {
        $this->logger->expects()
            ->debug('AMQPConnection::connect() connection attempt', [
                'config' => ['my' => 'loggable connection config'],
            ])
            ->once();

        $this->amqpConnection->connect();
    }

    public function testConnectThrowsCompatibleExceptionOnFailure(): void
    {
        $amqplibException = new AMQPIOException('Bang! from amqplib');
        $this->amqpIntegration->allows()
            ->connect($this->connectionConfig)
            ->andThrow($amqplibException);

        $this->expectException(AMQPConnectionException::class);
        // Raise php-amqp/ext-amqp -compatible exception: see next test for detailed logger handling.
        $this->expectExceptionMessage('Socket error: could not connect to host.');

        $this->amqpConnection->connect();
    }

    public function testConnectLogsSpecificAmqplibExceptionViaLoggerOnFailure(): void
    {
        $amqplibException = new AMQPIOException('Bang! from amqplib');
        $this->amqpIntegration->allows()
            ->connect($this->connectionConfig)
            ->andThrow($amqplibException);

        $this->logger->expects()
            ->error('AMQPConnection::connect() failed', [
                'exception' => $amqplibException::class,
                'message' => 'Bang! from amqplib',
            ])
            ->once();

        try {
            $this->amqpConnection->connect();
        } catch (AMQPConnectionException) {}
    }

    public function testGetConnectionNameFetchesFromConfig(): void
    {
        static::assertSame('my-connection-name', $this->amqpConnection->getConnectionName());
    }

    public function testGetHeartbeatIntervalFetchesFromConfig(): void
    {
        static::assertSame(123, $this->amqpConnection->getHeartbeatInterval());
    }

    public function testGetHostFetchesFromConfig(): void
    {
        static::assertSame('my.host', $this->amqpConnection->getHost());
    }

    public function testGetLoginFetchesUserFromConfig(): void
    {
        static::assertSame('myuser', $this->amqpConnection->getLogin());
    }

    public function testGetPasswordFetchesFromConfig(): void
    {
        static::assertSame('mypa55w0rd', $this->amqpConnection->getPassword());
    }

    public function testGetPortFetchesFromConfig(): void
    {
        static::assertSame(4321, $this->amqpConnection->getPort());
    }

    public function testGetReadTimeoutFetchesFromConfig(): void
    {
        static::assertSame(12.34, $this->amqpConnection->getReadTimeout());
    }

    public function testGetRpcTimeoutFetchesFromConfig(): void
    {
        static::assertSame(56.78, $this->amqpConnection->getRpcTimeout());
    }

    public function testGetTimeoutFetchesReadTimeoutFromConfig(): void
    {
        static::assertSame(12.34, $this->amqpConnection->getTimeout());
    }

    public function testGetTimeoutRaisesDeprecationWarning(): void
    {
        $triggeredErrorNumber = null;
        $triggeredErrorMessage = null;
        set_error_handler(
            function (int $number, string $string) use (&$triggeredErrorNumber, &$triggeredErrorMessage) {
                $triggeredErrorNumber = $number;
                $triggeredErrorMessage = $string;
            },
            E_USER_DEPRECATED
        );

        try {
            $result = $this->amqpConnection->getTimeout();
        } finally {
            restore_error_handler();
        }

        static::assertSame(12.34, $result);
        static::assertSame(E_USER_DEPRECATED, $triggeredErrorNumber);
        static::assertSame(
            'AMQPConnection::getTimeout() method is deprecated; ' .
            'use AMQPConnection::getReadTimeout() instead',
            $triggeredErrorMessage
        );
    }

    public function testGetVHostFetchesFromConfig(): void
    {
        static::assertSame('/my/vhost', $this->amqpConnection->getVhost());
    }

    public function testGetWriteTimeoutFetchesFromConfig(): void
    {
        static::assertSame(9.1, $this->amqpConnection->getWriteTimeout());
    }

    public function testIsConnectedReturnsFalseBeforeConnecting(): void
    {
        static::assertFalse($this->amqpConnection->isConnected());
    }

    public function testIsConnectedReturnsTrueAfterConnecting(): void
    {
        $this->amqpConnection->connect();

        static::assertTrue($this->amqpConnection->isConnected());
    }

    public function testIsPersistentAlwaysReturnsFalse(): void
    {
        static::assertFalse($this->amqpConnection->isPersistent());
    }

    public function testSetConnectionNameSetsTheNameOnConfig(): void
    {
        $this->connectionConfig->expects()
            ->setConnectionName('my-new-connection-name')
            ->once();

        $this->amqpConnection->setConnectionName('my-new-connection-name');
    }

    // TODO: Test (and implement) remaining API.
}
