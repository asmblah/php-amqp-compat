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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\Amqp;

use AMQPConnection;
use AMQPConnectionException;
use Asmblah\PhpAmqpCompat\AmqpManager;
use Asmblah\PhpAmqpCompat\Bridge\AmqpBridge;
use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Connection\Config\ConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Connection\Config\TimeoutDeprecationUsageEnum;
use Asmblah\PhpAmqpCompat\Error\ErrorReporterInterface;
use Asmblah\PhpAmqpCompat\Integration\AmqpIntegrationInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Mockery;
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
     * @var (MockInterface&ErrorReporterInterface)|null
     */
    private $errorReporter;
    /**
     * @var (MockInterface&LoggerInterface)|null
     */
    private $logger;

    public function setUp(): void
    {
        $this->amqplibConnection = mock(AmqplibConnection::class, [
            'isConnected' => true,
        ]);
        $this->connectionBridge = mock(AmqpConnectionBridgeInterface::class, [
            'getAmqplibConnection' => $this->amqplibConnection,
            'getUsedChannels' => 9998,
        ]);
        $this->connectionConfig = mock(ConnectionConfigInterface::class, [
            'getConnectionName' => 'my-connection-name',
            'getConnectionTimeout' => 0,
            'getDeprecatedTimeoutCredentialUsage' => TimeoutDeprecationUsageEnum::NOT_USED,
            'getDeprecatedTimeoutIniSettingUsage' => TimeoutDeprecationUsageEnum::NOT_USED,
            'getHeartbeatInterval' => 123,
            'getHost' => 'my.host',
            'getMaxChannels' => 456,
            'getMaxFrameSize' => 567,
            'getPassword' => 'mypa55w0rd',
            'getPort' => 4321,
            'getReadTimeout' => 12.34,
            'getRpcTimeout' => 56.78,
            'getUser' => 'myuser',
            'getVirtualHost' => '/my/vhost',
            'getWriteTimeout' => 9.1,
            'toLoggableArray' => ['my' => 'loggable connection config'],
        ]);
        $this->errorReporter = mock(ErrorReporterInterface::class, [
            'raiseDeprecation' => null,
            'raiseNotice' => null,
            'raiseWarning' => null,
        ]);
        $this->logger = mock(LoggerInterface::class, [
            'debug' => null,
            'error' => null,
        ]);
        $this->amqpIntegration = mock(AmqpIntegrationInterface::class, [
            'connect' => $this->connectionBridge,
            'createConnectionConfig' => $this->connectionConfig,
            'getErrorReporter' => $this->errorReporter,
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

    public function testConstructorRaisesNoErrorWhenNeitherDeprecatedTimeoutCredentialNorIniSettingUsed(): void
    {
        $this->errorReporter->expects()
            ->raiseDeprecation(Mockery::any())
            ->never();
        $this->errorReporter->expects()
            ->raiseNotice(Mockery::any())
            ->never();

        new AMQPConnection();
    }

    public function testConstructorRaisesOnlyDeprecationWhenDeprecatedTimeoutCredentialUsedAlone(): void
    {
        $this->connectionConfig->allows()
            ->getDeprecatedTimeoutCredentialUsage()
            ->andReturn(TimeoutDeprecationUsageEnum::USED_ALONE);

        $this->errorReporter->expects()
            ->raiseDeprecation(
                'AMQPConnection::__construct(): Parameter \'timeout\' is deprecated; ' .
                'use \'read_timeout\' instead'
            )
            ->once();
        $this->errorReporter->expects()
            ->raiseNotice(Mockery::any())
            ->never();

        new AMQPConnection(['timeout' => 12.34]);
    }

    public function testConstructorRaisesOnlyNoticeWhenDeprecatedTimeoutCredentialShadowed(): void
    {
        $this->connectionConfig->allows()
            ->getDeprecatedTimeoutCredentialUsage()
            ->andReturn(TimeoutDeprecationUsageEnum::SHADOWED);

        $this->errorReporter->expects()
            ->raiseDeprecation(Mockery::any())
            ->never();
        $this->errorReporter->expects()
            ->raiseNotice(
                'AMQPConnection::__construct(): Parameter \'timeout\' is deprecated, ' .
                '\'read_timeout\' used instead'
            )
            ->once();

        new AMQPConnection(['timeout' => 12.34, 'read_timeout' => 56.78]);
    }

    public function testConstructorRaisesOnlyDeprecationWhenDeprecatedTimeoutIniSettingUsedAlone(): void
    {
        $this->connectionConfig->allows()
            ->getDeprecatedTimeoutIniSettingUsage()
            ->andReturn(TimeoutDeprecationUsageEnum::USED_ALONE);

        $this->errorReporter->expects()
            ->raiseDeprecation(
                'AMQPConnection::__construct(): INI setting \'amqp.timeout\' is deprecated; ' .
                'use \'amqp.read_timeout\' instead'
            )
            ->once();
        $this->errorReporter->expects()
            ->raiseNotice(Mockery::any())
            ->never();

        new AMQPConnection(['timeout' => 12.34]);
    }

    public function testConstructorRaisesBothDeprecationAndNoticeWhenDeprecatedTimeoutIniSettingShadowed(): void
    {
        $this->connectionConfig->allows()
            ->getDeprecatedTimeoutIniSettingUsage()
            ->andReturn(TimeoutDeprecationUsageEnum::SHADOWED);

        $this->errorReporter->expects()
            ->raiseDeprecation(
                'AMQPConnection::__construct(): INI setting \'amqp.timeout\' is deprecated; ' .
                'use \'amqp.read_timeout\' instead'
            )
            ->once();
        $this->errorReporter->expects()
            ->raiseNotice(
                'AMQPConnection::__construct(): INI setting \'amqp.read_timeout\' will be used instead of \'amqp.timeout\''
            )
            ->once();

        new AMQPConnection(['timeout' => 12.34, 'read_timeout' => 56.78]);
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

    public function testGetMaxChannelsFetchesMaxChannelsFromConfig(): void
    {
        static::assertSame(456, $this->amqpConnection->getMaxChannels());
    }

    public function testGetMaxFrameSizeFetchesMaxFrameSizeFromConfig(): void
    {
        static::assertSame(567, $this->amqpConnection->getMaxFrameSize());
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
        $this->errorReporter->expects()
            ->raiseDeprecation(
                'AMQPConnection::getTimeout() method is deprecated; ' .
                'use AMQPConnection::getReadTimeout() instead'
            )
            ->once();

        $this->amqpConnection->getTimeout();
    }

    public function testGetUsedChannelsRaisesWarningWhenNotConnected(): void
    {
        $this->errorReporter->expects()
            ->raiseWarning('AMQPConnection::getUsedChannels(): Connection is not connected.')
            ->once();

        $this->amqpConnection->getUsedChannels();
    }

    public function testGetUsedChannelsReturns0WhenNotConnected(): void
    {
        static::assertSame(0, $this->amqpConnection->getUsedChannels());
    }

    public function testGetUsedChannelsFetchesResultFromConnectionBridgeWhenConnected(): void
    {
        $this->amqpConnection->connect();

        static::assertSame(9998, $this->amqpConnection->getUsedChannels());
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

    public function testSetTimeoutSetsTheNameOnConfig(): void
    {
        $this->connectionConfig->expects()
            ->setReadTimeout(12.34)
            ->once();

        $this->amqpConnection->setTimeout(12.34);
    }

    public function testSetTimeoutRaisesDeprecationWarning(): void
    {
        $this->connectionConfig->allows()
            ->setReadTimeout(12.34);

        $this->errorReporter->expects()
            ->raiseDeprecation(
                'AMQPConnection::setTimeout($timeout) method is deprecated; ' .
                'use AMQPConnection::setReadTimeout($timeout) instead'
            )
            ->once();

        $this->amqpConnection->setTimeout(12.34);
    }

    // TODO: Test (and implement) remaining API.
}
