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
 * Class SocketConfigurationFailedException.
 *
 * Raised when configuration of an open socket fails.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class SocketConfigurationFailedException extends Exception implements ExceptionInterface
{
}
