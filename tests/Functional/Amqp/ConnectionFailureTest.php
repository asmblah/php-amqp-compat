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

namespace Asmblah\PhpAmqpCompat\Tests\Functional\Amqp;

use AMQPConnection;
use AMQPConnectionException;
use Asmblah\PhpAmqpCompat\AmqpManager;
use Asmblah\PhpAmqpCompat\Configuration\Configuration;
use Asmblah\PhpAmqpCompat\Tests\Functional\AbstractFunctionalTestCase;
use Hamcrest\Arrays\IsArrayContainingInAnyOrder;
use Hamcrest\Text\StringContains;
use Mockery\MockInterface;
use PhpAmqpLib\Exception\AMQPIOException;
use Psr\Log\LoggerInterface;

/**
 * Class ConnectionFailureTest.
 *
 * Checks connection failure handling when the AMQP broker server cannot be contacted at all
 * (attempts a real connection).
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ConnectionFailureTest extends AbstractFunctionalTestCase
{
    private MockInterface&LoggerInterface $logger;

    public function setUp(): void
    {
        parent::setUp();

        $this->logger = mock(LoggerInterface::class, [
            'critical' => null,
            'log' => null,
        ]);

        $this->resetAmqpManager();
        AmqpManager::setConfiguration(new Configuration($this->logger));
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $this->resetAmqpManager();
    }

    public function testConnectionFailureIsHandledCorrectly(): void
    {
        $amqpConnection = new AMQPConnection([
            'connect_timeout' => 0.01,
            'host' => 'my.invalid.host',
        ]);

        $this->expectException(AMQPConnectionException::class);
        $this->expectExceptionMessage('Socket error: could not connect to host.');
        $this->logger->expects()
            ->critical(
                'AMQPConnection::connect(): Amqplib failure',
                IsArrayContainingInAnyOrder::arrayContainingInAnyOrder([
                    'exception' => AMQPIOException::class,
                    'message' => StringContains::containsString(
                        'stream_socket_client(): Unable to connect to tcp://my.invalid.host:5672 ' .
                        '(php_network_getaddresses: getaddrinfo for my.invalid.host failed'
                    ),
                    'code' => 0,
                ])
            )
            ->once();

        $amqpConnection->connect();
    }
}
