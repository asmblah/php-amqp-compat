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
 * Class TooManyChannelsOnConnectionException.
 *
 * Raised when the number of channels on a connection would exceed PHP_AMQP_MAX_CHANNELS.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class TooManyChannelsOnConnectionException extends Exception implements ExceptionInterface
{
}
