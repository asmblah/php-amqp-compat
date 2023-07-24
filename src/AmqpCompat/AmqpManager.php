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

namespace Asmblah\PhpAmqpCompat;

use Asmblah\PhpAmqpCompat\Heartbeat\PcntlHeartbeatSender;
use Asmblah\PhpAmqpCompat\Misc\Clock;

class AmqpManager
{
    /**
     * @var AmqpFactoryInterface|null
     */
    private static $amqpFactory = null;

    /**
     * Fetches the AmqpFactory to use. Will create one by default if not overridden.
     */
    public static function getAmqpFactory(): AmqpFactoryInterface
    {
        if (self::$amqpFactory === null) {
            self::$amqpFactory = new AmqpFactory(new PcntlHeartbeatSender(new Clock()));
        }

        return self::$amqpFactory;
    }

    /**
     * Overrides the AmqpFactory to use.
     */
    public static function setAmqpFactory(AmqpFactoryInterface $amqpFactory): void
    {
        self::$amqpFactory = $amqpFactory;
    }
}
