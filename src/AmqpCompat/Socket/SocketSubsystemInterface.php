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

namespace Asmblah\PhpAmqpCompat\Socket;

use Asmblah\PhpAmqpCompat\Exception\SocketConfigurationFailedException;
use Socket;

/**
 * Interface SocketSubsystemInterface.
 *
 * Manages sockets.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface SocketSubsystemInterface
{
    /**
     * Sets the read timeout of an open socket.
     *
     * @throws SocketConfigurationFailedException If the socket read timeout change fails.
     */
    public function setSocketReadTimeout(Socket $socket, float $seconds): void;

    /**
     * Sets the write timeout of an open socket.
     *
     * @throws SocketConfigurationFailedException If the socket write timeout change fails.
     */
    public function setSocketWriteTimeout(Socket $socket, float $seconds): void;
}
