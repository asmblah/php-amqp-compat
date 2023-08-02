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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\AmqpCompat;

use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Configuration\ConfigurationInterface;
use Asmblah\PhpAmqpCompat\Connection\ConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Connection\ConnectorInterface;
use Asmblah\PhpAmqpCompat\Heartbeat\HeartbeatSenderInterface;
use Asmblah\PhpAmqpCompat\Integration\AmqpIntegration;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Mockery;
use Mockery\MockInterface;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AbstractConnection as AmqplibConnection;
use Psr\Log\LoggerInterface;

/**
 * Class AmqpIntegrationTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqpIntegrationTest extends AbstractTestCase
{
    private ?AmqpIntegration $amqpIntegration;
    /**
     * @var (MockInterface&AmqplibConnection)|null
     */
    private $amqplibConnection;
    /**
     * @var (MockInterface&ConnectionConfigInterface)|null
     */
    private $connectionConfig;
    /**
     * @var (MockInterface&ConfigurationInterface)|null
     */
    private $configuration;
    /**
     * @var (MockInterface&ConnectorInterface)|null
     */
    private $connector;
    /**
     * @var (MockInterface&HeartbeatSenderInterface)|null
     */
    private $heartbeatSender;
    /**
     * @var (MockInterface&LoggerInterface)|null
     */
    private $logger;

    public function setUp(): void
    {
        $this->amqplibConnection = mock(AbstractConnection::class);
        $this->logger = mock(LoggerInterface::class);
        $this->configuration = mock(ConfigurationInterface::class, [
            'getLogger' => $this->logger,
        ]);
        $this->connectionConfig = mock(ConnectionConfigInterface::class);
        $this->connector = mock(ConnectorInterface::class, [
            'connect' => $this->amqplibConnection,
        ]);
        $this->heartbeatSender = mock(HeartbeatSenderInterface::class, [
            'register' => null,
        ]);

        $this->amqpIntegration = new AmqpIntegration($this->connector, $this->heartbeatSender, $this->configuration);
    }

    public function testConnectConnectsViaTheConnector(): void
    {
        $this->connector->expects()
            ->connect($this->connectionConfig)
            ->once();

        $this->amqpIntegration->connect($this->connectionConfig);
    }

    public function testConnectReturnsAConnectionBridgeUsingTheAmqplibConnection(): void
    {
        $connectionBridge = $this->amqpIntegration->connect($this->connectionConfig);

        static::assertSame($this->amqplibConnection, $connectionBridge->getAmqplibConnection());
    }

    public function testConnectRegistersTheCreatedBridgeWithTheHeartbeatSender(): void
    {
        $this->heartbeatSender->expects()
            ->register(Mockery::type(AmqpConnectionBridgeInterface::class))
            ->once();

        $this->amqpIntegration->connect($this->connectionConfig);
    }

    public function testCreateConnectionConfigUsesCorrectDefaults(): void
    {
        $config = $this->amqpIntegration->createConnectionConfig([]);

        static::assertSame('localhost', $config->getHost());
        static::assertSame(5672, $config->getPort());
        static::assertSame('guest', $config->getUser());
        static::assertSame('guest', $config->getPassword());
        static::assertSame('/', $config->getVirtualHost());
        static::assertSame(0, $config->getHeartbeatInterval());
        static::assertSame(ConnectionConfigInterface::DEFAULT_CONNECTION_TIMEOUT, $config->getConnectionTimeout());
        static::assertSame(ConnectionConfigInterface::DEFAULT_READ_TIMEOUT, $config->getReadTimeout());
        static::assertSame(ConnectionConfigInterface::DEFAULT_WRITE_TIMEOUT, $config->getWriteTimeout());
        static::assertSame(ConnectionConfigInterface::DEFAULT_RPC_TIMEOUT, $config->getRpcTimeout());
    }

    public function testCreateConnectionConfigUsesGivenSettings(): void
    {
        $config = $this->amqpIntegration->createConnectionConfig([
            'host' => 'myhost',
            'port' => 1234,
            'login' => 'myuser',
            'password' => 'my password',
            'vhost' => '/my/vhost',
            'heartbeat' => 123,
            'connect_timeout' => 12.34,
            'read_timeout' => 56.78,
            'write_timeout' => 90.12,
            'rpc_timeout' => 34.56
        ]);

        static::assertSame('myhost', $config->getHost());
        static::assertSame(1234, $config->getPort());
        static::assertSame('myuser', $config->getUser());
        static::assertSame('my password', $config->getPassword());
        static::assertSame('/my/vhost', $config->getVirtualHost());
        static::assertSame(123, $config->getHeartbeatInterval());
        static::assertSame(12.34, $config->getConnectionTimeout());
        static::assertSame(56.78, $config->getReadTimeout());
        static::assertSame(90.12, $config->getWriteTimeout());
        static::assertSame(34.56, $config->getRpcTimeout());
    }
}
