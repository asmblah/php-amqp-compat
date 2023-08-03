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

namespace Asmblah\PhpAmqpCompat\Tests\Functional\Amqp;

use AMQPConnection;
use AMQPConnectionException;
use Asmblah\PhpAmqpCompat\AmqpManager;
use Asmblah\PhpAmqpCompat\Configuration\Configuration;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Mockery\MockInterface;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use Psr\Log\LoggerInterface;

/**
 * Class LoginFailureTest.
 *
 * Talks to a real AMQP broker server instance, checking login failure handling.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class LoginFailureTest extends AbstractTestCase
{
    /**
     * @var (MockInterface&LoggerInterface)|null
     */
    private $logger;

    public function setUp(): void
    {
        $this->logger = mock(LoggerInterface::class, [
            'debug' => null,
            'error' => null,
        ]);

        AmqpManager::setConfiguration(new Configuration($this->logger));
    }

    public function tearDown(): void
    {
        AmqpManager::setAmqpIntegration(null);
        AmqpManager::setConfiguration(null);
    }

    public function testLoginFailureIsHandledCorrectly(): void
    {
        $amqpConnection = new AMQPConnection([
            'login' => 'wronguser',
            'password' => 'wrongpassword',
        ]);

        $this->expectException(AMQPConnectionException::class);
        $this->expectExceptionMessage(
            'Library error: connection closed unexpectedly - Potential login failure.'
        );
        $this->logger->expects()
            ->error(
                'AMQPConnection::connect() failed',
                [
                    'exception' => AMQPConnectionClosedException::class,
                    'message' => 'ACCESS_REFUSED - Login was refused using authentication mechanism AMQPLAIN. ' .
                        'For details see the broker logfile.(0, 0)'
                ]
            )
            ->once();

        $amqpConnection->connect();
    }
}