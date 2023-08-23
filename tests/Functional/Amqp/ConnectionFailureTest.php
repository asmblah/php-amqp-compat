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
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
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
class ConnectionFailureTest extends AbstractTestCase
{
    /**
     * @var (MockInterface&LoggerInterface)|null
     */
    private $logger;

    public function setUp(): void
    {
        $this->logger = mock(LoggerInterface::class, [
            'error' => null,
            'log' => null,
        ]);

        AmqpManager::setConfiguration(new Configuration($this->logger));
    }

    public function tearDown(): void
    {
        AmqpManager::setAmqpIntegration(null);
        AmqpManager::setConfiguration(null);
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
            ->error(
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
