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

use Asmblah\PhpAmqpCompat\Connection\Config\ConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Connection\ConnectorInterface;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transport\Transport;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transport\TransportConnector;
use Asmblah\PhpAmqpCompat\Socket\SocketSubsystemInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Mockery\MockInterface;
use PhpAmqpLib\Connection\AbstractConnection as AmqplibConnection;

/**
 * Class TransportConnectorTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class TransportConnectorTest extends AbstractTestCase
{
    private MockInterface&AmqplibConnection $amqplibConnection;
    private MockInterface&ConnectionConfigInterface $connectionConfig;
    private MockInterface&ConnectorInterface $connector;
    private MockInterface&SocketSubsystemInterface $socketSubsystem;
    private TransportConnector $transportConnector;

    public function setUp(): void
    {
        $this->amqplibConnection = mock(AmqplibConnection::class);
        $this->connectionConfig = mock(ConnectionConfigInterface::class);
        $this->connector = mock(ConnectorInterface::class);
        $this->socketSubsystem = mock(SocketSubsystemInterface::class);

        $this->transportConnector = new TransportConnector(
            $this->connector,
            $this->socketSubsystem
        );
    }

    public function testConnectCallsConnector(): void
    {
        $this->connector->expects()
            ->connect($this->connectionConfig)
            ->once()
            ->andReturn($this->amqplibConnection);

        $this->transportConnector->connect($this->connectionConfig);
    }

    public function testConnectReturnsCorrectlyCreatedTransport(): void
    {
        $this->connector->allows()
            ->connect($this->connectionConfig)
            ->andReturn($this->amqplibConnection);

        /** @var Transport $transport */
        $transport = $this->transportConnector->connect($this->connectionConfig);

        static::assertInstanceOf(Transport::class, $transport);
        static::assertSame($this->amqplibConnection, $transport->getAmqplibConnection());
    }
}
