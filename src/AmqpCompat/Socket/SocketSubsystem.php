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
 * Class SocketSubsystem.
 *
 * Manages sockets.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class SocketSubsystem implements SocketSubsystemInterface
{
    /**
     * @inheritDoc
     */
    public function setSocketReadTimeout(Socket $socket, float $seconds): void
    {
        $wholeSeconds = floor($seconds);
        $additionalMicroseconds = ($seconds - $wholeSeconds) * 1000000;
        $timeValue = ['sec' => $wholeSeconds, 'usec' => $additionalMicroseconds];

        if (!socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, $timeValue)) {
            throw new SocketConfigurationFailedException('Could not set socket read timeout');
        }
    }

    /**
     * @inheritDoc
     */
    public function setSocketWriteTimeout(Socket $socket, float $seconds): void
    {
        $wholeSeconds = floor($seconds);
        $additionalMicroseconds = ($seconds - $wholeSeconds) * 1000000;
        $timeValue = ['sec' => $wholeSeconds, 'usec' => $additionalMicroseconds];

        if (!socket_set_option($socket, SOL_SOCKET, SO_SNDTIMEO, $timeValue)) {
            throw new SocketConfigurationFailedException('Could not set socket write timeout');
        }
    }
}
