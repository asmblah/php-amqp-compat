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

use Asmblah\PhpAmqpCompat\AmqpFactory;
use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Connection\ConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Connection\ConnectorInterface;
use Asmblah\PhpAmqpCompat\Heartbeat\HeartbeatSenderInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Mockery;
use Mockery\MockInterface;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AbstractConnection as AmqplibConnection;

/**
 * Class AmqpFactoryTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqpFactoryTest extends AbstractTestCase
{
    private ?AmqpFactory $amqpFactory;
    /**
     * @var (MockInterface&AmqplibConnection)|null
     */
    private $amqplibConnection;
    /**
     * @var (MockInterface&ConnectionConfigInterface)|null
     */
    private $config;
    /**
     * @var (MockInterface&ConnectorInterface)|null
     */
    private $connector;
    /**
     * @var (MockInterface&HeartbeatSenderInterface)|null
     */
    private $heartbeatSender;

    public function setUp(): void
    {
        $this->amqplibConnection = mock(AbstractConnection::class);
        $this->config = mock(ConnectionConfigInterface::class);
        $this->connector = mock(ConnectorInterface::class, [
            'connect' => $this->amqplibConnection,
        ]);
        $this->heartbeatSender = mock(HeartbeatSenderInterface::class, [
            'register' => null,
        ]);

        $this->amqpFactory = new AmqpFactory($this->connector, $this->heartbeatSender);
    }

    public function testConnectConnectsViaTheConnector(): void
    {
        $this->connector->expects()
            ->connect($this->config)
            ->once();

        $this->amqpFactory->connect($this->config);
    }

    public function testConnectReturnsAConnectionBridgeUsingTheAmqplibConnection(): void
    {
        $connectionBridge = $this->amqpFactory->connect($this->config);

        static::assertSame($this->amqplibConnection, $connectionBridge->getAmqplibConnection());
    }

    public function testConnectRegistersTheCreatedBridgeWithTheHeartbeatSender(): void
    {
        $this->heartbeatSender->expects()
            ->register(Mockery::type(AmqpConnectionBridgeInterface::class))
            ->once();

        $this->amqpFactory->connect($this->config);
    }

    public function testCreateConnectionConfigUsesCorrectDefaults(): void
    {
        $config = $this->amqpFactory->createConnectionConfig([]);

        static::assertSame('localhost', $config->getHost());
        static::assertSame(5672, $config->getPort());
        static::assertSame('guest', $config->getUser());
        static::assertSame('guest', $config->getPassword());
        static::assertSame('/', $config->getVirtualHost());
        static::assertSame(0, $config->getHeartbeatInterval());
        static::assertSame(3.0, $config->getConnectionTimeout());
        static::assertSame(3.0, $config->getReadTimeout());
        static::assertSame(3.0, $config->getWriteTimeout());
        static::assertSame(0.0, $config->getRpcTimeout());
    }

    public function testCreateConnectionConfigUsesGivenSettings(): void
    {
        $config = $this->amqpFactory->createConnectionConfig([
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
