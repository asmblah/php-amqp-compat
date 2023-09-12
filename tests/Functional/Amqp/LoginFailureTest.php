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
            'critical' => null,
            'log' => null,
        ]);

        $this->resetAmqpManager();
        AmqpManager::setConfiguration(new Configuration($this->logger));
    }

    public function tearDown(): void
    {
        $this->resetAmqpManager();
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
            ->critical(
                'AMQPConnection::connect(): Amqplib failure',
                [
                    'exception' => AMQPConnectionClosedException::class,
                    'message' => 'ACCESS_REFUSED - Login was refused using authentication mechanism AMQPLAIN. ' .
                        'For details see the broker logfile.(0, 0)',
                    'code' => 403,
                ]
            )
            ->once();

        $amqpConnection->connect();
    }
}
