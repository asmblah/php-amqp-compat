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

use Asmblah\PhpAmqpCompat\Connection\Connector;
use Asmblah\PhpAmqpCompat\Heartbeat\PcntlHeartbeatSender;
use Asmblah\PhpAmqpCompat\Misc\Clock;

/**
 * Class AmqpManager.
 *
 * Allows the AmqpFactory to be replaced while supporting ext-amqp's API
 * that does not allow for dependency injection, providing the default implementation otherwise.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqpManager
{
    private static ?AmqpFactoryInterface $amqpFactory = null;

    /**
     * Fetches the AmqpFactory to use. Will create one by default if not overridden.
     */
    public static function getAmqpFactory(): AmqpFactoryInterface
    {
        if (self::$amqpFactory === null) {
            self::$amqpFactory = new AmqpFactory(
                new Connector(),
                new PcntlHeartbeatSender(new Clock())
            );
        }

        return self::$amqpFactory;
    }

    /**
     * Overrides the AmqpFactory to use.
     */
    public static function setAmqpFactory(?AmqpFactoryInterface $amqpFactory): void
    {
        self::$amqpFactory = $amqpFactory;
    }
}
