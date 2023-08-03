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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\AmqpCompat\Connection;

use Asmblah\PhpAmqpCompat\Connection\Amqplib\ConnectionFactoryInterface;
use Asmblah\PhpAmqpCompat\Connection\Config\ConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Connection\Connector;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Mockery;
use Mockery\MockInterface;
use PhpAmqpLib\Connection\AMQPConnectionConfig;

/**
 * Class ConnectorTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ConnectorTest extends AbstractTestCase
{
    /**
     * @var (MockInterface&ConnectionConfigInterface)|null
     */
    private $connectionConfig;
    /**
     * @var (MockInterface&ConnectionFactoryInterface)|null
     */
    private $connectionFactory;
    private ?Connector $connector;

    public function setUp(): void
    {
        $this->connectionConfig = mock(ConnectionConfigInterface::class, [
            'getConnectionTimeout' => 0,
            'getHeartbeatInterval' => 12,
            'getHost' => 'myhost',
            'getPassword' => 'mypass',
            'getPort' => 321,
            'getReadTimeout' => 0,
            'getRpcTimeout' => 0,
            'getUser' => 'myuser',
            'getVirtualHost' => '/my/vhost',
            'getWriteTimeout' => 0,
        ]);
        $this->connectionFactory = mock(ConnectionFactoryInterface::class);

        $this->connector = new Connector($this->connectionFactory, 1234);
    }

    public function testConnectProvidesAllConfiguration(): void
    {
        $this->connectionConfig->allows()->getConnectionTimeout()->andReturn(987);
        $this->connectionConfig->allows()->getReadTimeout()->andReturn(654);
        $this->connectionConfig->allows()->getRpcTimeout()->andReturn(333);
        $this->connectionConfig->allows()->getWriteTimeout()->andReturn(222);

        $this->connectionFactory->expects()
            ->connect(
                'myhost',
                321,
                'myuser',
                'mypass',
                '/my/vhost',
                false,
                AMQPConnectionConfig::AUTH_AMQPPLAIN,
                'en_US',
                987,
                654,
                false,
                12,
                333
            )
            ->once();

        $this->connector->connect($this->connectionConfig);
    }

    public function testConnectProvidesUnlimitedTimeoutsWhenSetToZero(): void
    {
        $this->connectionFactory->expects()
            ->connect(
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                Mockery::any(),
                1234,
                1234,
                Mockery::any(),
                Mockery::any(),
                1234
            )
            ->once();

        $this->connector->connect($this->connectionConfig);
    }
}
