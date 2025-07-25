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
use Asmblah\PhpAmqpCompat\AmqpManager;
use Asmblah\PhpAmqpCompat\Bridge\AmqpBridge;
use Asmblah\PhpAmqpCompat\Configuration\Configuration;
use Asmblah\PhpAmqpCompat\Tests\Functional\AbstractFunctionalTestCase;
use Mockery\MockInterface;
use PhpAmqpLib\Wire\IO\StreamIO;
use Psr\Log\LoggerInterface;

/**
 * Class ConnectionTimeoutTuningTest.
 *
 * Checks connection timeout tuning against a real AMQP broker server.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ConnectionTimeoutTuningTest extends AbstractFunctionalTestCase
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

    public function testConnectionReadTimeoutMayBeChangedOnline(): void
    {
        $amqpConnection = new AMQPConnection(['read_timeout' => 10]);
        $amqpConnection->connect();

        $amqpConnection->setReadTimeout(18.5);

        static::assertTrue($amqpConnection->isConnected());
        static::assertSame(18.5, $amqpConnection->getReadTimeout());
        $amqplibConnection = AmqpBridge::getBridgeConnection($amqpConnection)->getAmqplibConnection();
        static::assertSame(
            18.5,
            $amqplibConnection->getReadTimeout()
        );
        /** @var StreamIO $streamIo */
        $streamIo = $amqplibConnection->getIO();
        $socket = socket_import_stream($streamIo->getSocket());
        static::assertEquals(
            [
                'sec' => 18,
                'usec' => 500000, // 0.5s in microseconds above the 18s.
            ],
            socket_get_option($socket, SOL_SOCKET, SO_RCVTIMEO),
            'Underlying socket SO_RCVTIMEO option should be changed to 18.5s'
        );
    }
}
