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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\AmqpCompat\Connection\Config;

use Asmblah\PhpAmqpCompat\Connection\Config\ConnectionConfig;
use Asmblah\PhpAmqpCompat\Connection\Config\DefaultConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Connection\Config\TimeoutDeprecationUsageEnum;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Mockery\MockInterface;

/**
 * Class ConnectionConfigTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ConnectionConfigTest extends AbstractTestCase
{
    private ?ConnectionConfig $config;
    /**
     * @var (MockInterface&DefaultConnectionConfigInterface)|null
     */
    private $defaultConnectionConfig;

    public function setUp(): void
    {
        $this->defaultConnectionConfig = mock(DefaultConnectionConfigInterface::class);

        $this->config = new ConnectionConfig($this->defaultConnectionConfig);
    }

    public function testGettersReturnCorrectValues(): void
    {
        // Test the getters in isolation by fetching values initialised by constructor.
        $config = new ConnectionConfig(
            $this->defaultConnectionConfig,
            'myhostname',
            1234,
            'myusername',
            'mysecretpassword',
            '/my/special/vhost',
            432,
            123.45,
            567.89,
            456.78,
            678.9,
            123,
            321,
            'my-special-connection-name'
        );

        static::assertSame('my-special-connection-name', $config->getConnectionName());
        static::assertSame(123.45, $config->getConnectionTimeout());
        static::assertSame(432, $config->getHeartbeatInterval());
        static::assertSame('myhostname', $config->getHost());
        static::assertSame(123, $config->getMaxChannels());
        static::assertSame(321, $config->getMaxFrameSize());
        static::assertSame('mysecretpassword', $config->getPassword());
        static::assertSame(1234, $config->getPort());
        static::assertSame(567.89, $config->getReadTimeout());
        static::assertSame(678.9, $config->getRpcTimeout());
        static::assertSame('myusername', $config->getUser());
        static::assertSame('/my/special/vhost', $config->getVirtualHost());
        static::assertSame(456.78, $config->getWriteTimeout());
    }

    public function testSettersAssignCorrectValues(): void
    {
        $this->config->setConnectionName('my-connection');
        $this->config->setConnectionTimeout(123.45);
        $this->config->setHeartbeatInterval(21);
        $this->config->setHost('myhost');
        $this->config->setMaxChannels(456);
        $this->config->setMaxFrameSize(789);
        $this->config->setPassword('secret');
        $this->config->setPort(321);
        $this->config->setReadTimeout(12.34);
        $this->config->setRpcTimeout(56.78);
        $this->config->setUser('myuser');
        $this->config->setVirtualHost('/my/vhost');
        $this->config->setWriteTimeout(98.76);

        static::assertSame('my-connection', $this->config->getConnectionName());
        static::assertSame(123.45, $this->config->getConnectionTimeout());
        static::assertSame(21, $this->config->getHeartbeatInterval());
        static::assertSame('myhost', $this->config->getHost());
        static::assertSame(456, $this->config->getMaxChannels());
        static::assertSame(789, $this->config->getMaxFrameSize());
        static::assertSame('secret', $this->config->getPassword());
        static::assertSame(321, $this->config->getPort());
        static::assertSame(12.34, $this->config->getReadTimeout());
        static::assertSame(56.78, $this->config->getRpcTimeout());
        static::assertSame('myuser', $this->config->getUser());
        static::assertSame('/my/vhost', $this->config->getVirtualHost());
        static::assertSame(98.76, $this->config->getWriteTimeout());
    }

    /**
     * @dataProvider deprecatedUsageDataProvider
     */
    public function testGetDeprecatedTimeoutCredentialUsageReturnsTheUsage(
        TimeoutDeprecationUsageEnum $deprecationUsage
    ): void {
        $config = new ConnectionConfig(
            $this->defaultConnectionConfig,
            DefaultConnectionConfigInterface::DEFAULT_HOST,
            DefaultConnectionConfigInterface::DEFAULT_PORT,
            DefaultConnectionConfigInterface::DEFAULT_USER,
            DefaultConnectionConfigInterface::DEFAULT_PASSWORD,
            DefaultConnectionConfigInterface::DEFAULT_VIRTUAL_HOST,
            DefaultConnectionConfigInterface::DEFAULT_HEARTBEAT_INTERVAL,
            DefaultConnectionConfigInterface::DEFAULT_CONNECTION_TIMEOUT,
            DefaultConnectionConfigInterface::DEFAULT_READ_TIMEOUT,
            DefaultConnectionConfigInterface::DEFAULT_WRITE_TIMEOUT,
            DefaultConnectionConfigInterface::DEFAULT_RPC_TIMEOUT,
            DefaultConnectionConfigInterface::DEFAULT_MAX_CHANNELS,
            DefaultConnectionConfigInterface::DEFAULT_MAX_FRAME_SIZE,
            null,
            $deprecationUsage
        );

        static::assertSame($deprecationUsage, $config->getDeprecatedTimeoutCredentialUsage());
    }

    /**
     * @dataProvider deprecatedUsageDataProvider
     */
    public function testGetDeprecatedTimeoutIniSettingUsageReturnsTheUsageFromDefaultConfig(
        TimeoutDeprecationUsageEnum $deprecationUsage
    ): void {
        $this->defaultConnectionConfig->allows()
            ->getDeprecatedTimeoutIniSettingUsage()
            ->andReturn($deprecationUsage);

        $config = new ConnectionConfig($this->defaultConnectionConfig);

        static::assertSame($deprecationUsage, $config->getDeprecatedTimeoutIniSettingUsage());
    }

    public function testToLoggableArrayReturnsCorrectDefaultStructure(): void
    {
        static::assertEquals(
            [
                'connection_name' => null,
                'connection_timeout' => DefaultConnectionConfigInterface::DEFAULT_CONNECTION_TIMEOUT,
                'heartbeat_interval' => DefaultConnectionConfigInterface::DEFAULT_HEARTBEAT_INTERVAL,
                'host' => DefaultConnectionConfigInterface::DEFAULT_HOST,
                'max_channels' => DefaultConnectionConfigInterface::DEFAULT_MAX_CHANNELS,
                'max_frame_size' => DefaultConnectionConfigInterface::DEFAULT_MAX_FRAME_SIZE,
                'password' => 'gu******', // Should be obfuscated.
                'port' => DefaultConnectionConfigInterface::DEFAULT_PORT,
                'read_timeout' => DefaultConnectionConfigInterface::DEFAULT_READ_TIMEOUT,
                'rpc_timeout' => DefaultConnectionConfigInterface::DEFAULT_RPC_TIMEOUT,
                'user' => DefaultConnectionConfigInterface::DEFAULT_USER,
                'virtual_host' => DefaultConnectionConfigInterface::DEFAULT_VIRTUAL_HOST,
                'write_timeout' => DefaultConnectionConfigInterface::DEFAULT_WRITE_TIMEOUT,
            ],
            $this->config->toLoggableArray()
        );
    }

    public function testToLoggableArrayReturnsCorrectConfiguredStructure(): void
    {
        $this->config->setConnectionName('my-connection');
        $this->config->setConnectionTimeout(123.45);
        $this->config->setHeartbeatInterval(21);
        $this->config->setHost('myhost');
        $this->config->setMaxChannels(89);
        $this->config->setMaxFrameSize(67);
        $this->config->setPassword('secret');
        $this->config->setPort(321);
        $this->config->setReadTimeout(12.34);
        $this->config->setRpcTimeout(56.78);
        $this->config->setUser('myuser');
        $this->config->setVirtualHost('/my/vhost');
        $this->config->setWriteTimeout(98.76);

        static::assertEquals(
            [
                'connection_name' => 'my-connection',
                'connection_timeout' => 123.45,
                'heartbeat_interval' => 21,
                'host' => 'myhost',
                'max_channels' => 89,
                'max_frame_size' => 67,
                'password' => 'se******',
                'port' => 321,
                'read_timeout' => 12.34,
                'rpc_timeout' => 56.78,
                'user' => 'myuser',
                'virtual_host' => '/my/vhost',
                'write_timeout' => 98.76,
            ],
            $this->config->toLoggableArray()
        );
    }

    public static function deprecatedUsageDataProvider(): array
    {
        return [
            '::NOT_USED' => [TimeoutDeprecationUsageEnum::NOT_USED],
            '::SHADOWED' => [TimeoutDeprecationUsageEnum::SHADOWED],
            '::USED_ALONE' => [TimeoutDeprecationUsageEnum::USED_ALONE],
        ];
    }
}
