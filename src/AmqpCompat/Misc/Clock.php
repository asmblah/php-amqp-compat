<?php

/*
 * PHP AMQP-Compat - php-amqp/ext-amqp compatibility.
 * Copyright (c) Dan Phillimore (asmblah)
 * https://github.com/asmblah/php-amqp-compat/
 *
 * Released under the MIT license.
 * https://github.com/asmblah/php-amqp-compat/raw/master/MIT-LICENSE.txt
 */

declare(strict_types=1);

namespace Asmblah\PhpAmqpCompat\Misc;

class Clock
{
    /**
     * Fetches the current Unix timestamp in seconds.
     *
     * Useful to allow stubbing.
     */
    public function getUnixTimestamp(): int
    {
        return time();
    }
}
