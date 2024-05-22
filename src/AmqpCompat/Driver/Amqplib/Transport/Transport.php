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

namespace Asmblah\PhpAmqpCompat\Driver\Amqplib\Transport;

use Asmblah\PhpAmqpCompat\Driver\Common\Transport\TransportInterface;
use Asmblah\PhpAmqpCompat\Exception\SocketConfigurationFailedException;
use Asmblah\PhpAmqpCompat\Exception\TransportConfigurationFailedException;
use Asmblah\PhpAmqpCompat\Socket\SocketSubsystemInterface;
use Closure;
use PhpAmqpLib\Connection\AbstractConnection as AmqplibConnection;
use PhpAmqpLib\Wire\IO\StreamIO;
use RuntimeException;
use Socket;

/**
 * Class Transport.
 *
 * Manages the connection with the underlying php-amqplib driver.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class Transport implements TransportInterface
{
    public function __construct(
        private readonly AmqplibConnection $amqplibConnection,
        private readonly SocketSubsystemInterface $socketSubsystem
    ) {
    }

    /**
     * Fetches the underlying php-amqplib connection.
     */
    public function getAmqplibConnection(): AmqplibConnection
    {
        return $this->amqplibConnection;
    }

    /**
     * @inheritDoc
     */
    public function setReadTimeout(float $seconds): void
    {
        $io = $this->amqplibConnection->getIO();

        if (!$io instanceof StreamIO) {
            throw new RuntimeException('Only StreamIO is supported');
        }

        // Note that this may be an issue for TLS connections: https://bugs.php.net/bug.php?id=70939
        $socket = socket_import_stream($io->getSocket());

        if (!$socket instanceof Socket) {
            throw new RuntimeException('Failed importing socket from stream');
        }

        try {
            $this->socketSubsystem->setSocketReadTimeout($socket, $seconds);
        } catch (SocketConfigurationFailedException $exception) {
            throw new TransportConfigurationFailedException(
                message: 'Could not set socket read timeout',
                previous: $exception
            );
        }

        Closure::bind(function () use ($seconds) {
            $this->read_timeout = $seconds;
        }, $io, StreamIO::class)();
    }
}
