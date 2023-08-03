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
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;

/**
 * Class ConnectionConfigTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ConnectionConfigTest extends AbstractTestCase
{
    private ?ConnectionConfig $config;

    public function setUp(): void
    {
        $this->config = new ConnectionConfig();
    }

    public function testGettersReturnCorrectValues(): void
    {
        // Test the getters in isolation by fetching values initialised by constructor.
        $config = new ConnectionConfig(
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
            'my-special-connection-name'
        );

        static::assertSame('my-special-connection-name', $config->getConnectionName());
        static::assertSame(123.45, $config->getConnectionTimeout());
        static::assertSame(432, $config->getHeartbeatInterval());
        static::assertSame('myhostname', $config->getHost());
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
        static::assertSame('secret', $this->config->getPassword());
        static::assertSame(321, $this->config->getPort());
        static::assertSame(12.34, $this->config->getReadTimeout());
        static::assertSame(56.78, $this->config->getRpcTimeout());
        static::assertSame('myuser', $this->config->getUser());
        static::assertSame('/my/vhost', $this->config->getVirtualHost());
        static::assertSame(98.76, $this->config->getWriteTimeout());
    }

    public function testToLoggableArrayReturnsCorrectDefaultStructure(): void
    {
        static::assertEquals(
            [
                'connection_name' => null,
                'connection_timeout' => 0.0,
                'heartbeat_interval' => 0.0,
                'host' => 'localhost',
                'password' => 'gu******',
                'port' => 5672,
                'read_timeout' => 0.0,
                'rpc_timeout' => 0.0,
                'user' => 'guest',
                'virtual_host' => '/',
                'write_timeout' => 0.0,
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
}
