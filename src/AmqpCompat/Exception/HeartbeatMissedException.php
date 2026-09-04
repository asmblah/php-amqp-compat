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

namespace Asmblah\PhpAmqpCompat\Exception;

use Exception;

/**
 * Class HeartbeatMissedException.
 *
 * Raised when a client or server heartbeat is missed.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class HeartbeatMissedException extends Exception implements ExceptionInterface
{
}
