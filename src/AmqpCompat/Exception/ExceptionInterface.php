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

use Throwable;

/**
 * Interface ExceptionInterface.
 *
 * Implemented by all PHP AMQP-Compat exceptions.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface ExceptionInterface extends Throwable
{
}
