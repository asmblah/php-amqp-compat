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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\AmqpCompat;

use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Configuration\ConfigurationInterface;
use Asmblah\PhpAmqpCompat\Connection\Config\ConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Connection\Config\DefaultConnectionConfigInterface;
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
     * @var (MockInterface&DefaultConnectionConfigInterface)|null
     */
    private $defaultConnectionConfig;
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
        $this->defaultConnectionConfig = mock(DefaultConnectionConfigInterface::class, [
            'getConnectionTimeout' => 123.0,
            'getHeartbeatInterval' => 234,
            'getHost' => 'my.default.host',
            'getPassword' => 'mydefaultpass',
            'getPort' => 345,
            'getReadTimeout' => 456.0,
            'getRpcTimeout' => 567.0,
            'getUser' => 'mydefaultuser',
            'getVirtualHost' => '/my/default/vhost',
            'getWriteTimeout' => 678.0,
        ]);
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

        $this->amqpIntegration = new AmqpIntegration(
            $this->connector,
            $this->heartbeatSender,
            $this->configuration,
            $this->defaultConnectionConfig
        );
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

        static::assertSame(123.0, $config->getConnectionTimeout());
        static::assertSame(234, $config->getHeartbeatInterval());
        static::assertSame('my.default.host', $config->getHost());
        static::assertSame('mydefaultpass', $config->getPassword());
        static::assertSame(345, $config->getPort());
        static::assertSame(456.0, $config->getReadTimeout());
        static::assertSame(567.0, $config->getRpcTimeout());
        static::assertSame('mydefaultuser', $config->getUser());
        static::assertSame('/my/default/vhost', $config->getVirtualHost());
        static::assertSame(678.0, $config->getWriteTimeout());
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
