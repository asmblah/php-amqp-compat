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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\AmqpCompat\Socket;

use Asmblah\PhpAmqpCompat\Exception\SocketConfigurationFailedException;
use Asmblah\PhpAmqpCompat\Socket\SocketSubsystem;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;

/**
 * Class SocketSubsystemTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class SocketSubsystemTest extends AbstractTestCase
{
    private SocketSubsystem $socketSubsystem;

    public function setUp(): void
    {
        $this->socketSubsystem = new SocketSubsystem();
    }

    public function testSetSocketReadTimeoutSetsCorrectly(): void
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        $this->socketSubsystem->setSocketReadTimeout($socket, 18.5);

        static::assertEquals(
            [
                'sec' => 18,
                'usec' => 500000, // 0.5s in microseconds above the 18s.
            ],
            socket_get_option($socket, SOL_SOCKET, SO_RCVTIMEO),
            'Socket SO_RCVTIMEO option should be changed to 18.5s'
        );
    }

    public function testSetSocketReadTimeoutRaisesExceptionOnFailure(): void
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        $this->expectException(SocketConfigurationFailedException::class);
        $this->expectExceptionMessage('Could not set socket read timeout');

        @$this->socketSubsystem->setSocketReadTimeout($socket, PHP_INT_MAX);
    }

    public function testSetSocketWriteTimeoutSetsCorrectly(): void
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        $this->socketSubsystem->setSocketWriteTimeout($socket, 18.5);

        static::assertEquals(
            [
                'sec' => 18,
                'usec' => 500000, // 0.5s in microseconds above the 18s.
            ],
            socket_get_option($socket, SOL_SOCKET, SO_SNDTIMEO),
            'Socket SO_SNDTIMEO option should be changed to 18.5s'
        );
    }

    public function testSetSocketWriteTimeoutRaisesExceptionOnFailure(): void
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        $this->expectException(SocketConfigurationFailedException::class);
        $this->expectExceptionMessage('Could not set socket write timeout');

        @$this->socketSubsystem->setSocketWriteTimeout($socket, PHP_INT_MAX);
    }
}
