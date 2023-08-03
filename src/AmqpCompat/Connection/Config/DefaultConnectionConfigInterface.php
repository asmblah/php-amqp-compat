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

namespace Asmblah\PhpAmqpCompat\Connection\Config;

/**
 * Interface DefaultConnectionConfigInterface.
 *
 * Represents the default configuration for an upcoming connection
 * that will be made by AMQPConnection, which will use INI settings.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface DefaultConnectionConfigInterface extends ConnectionConfigProviderInterface
{
    public const DEFAULT_CONNECTION_TIMEOUT = 0.0;
    public const DEFAULT_PORT = 5672;
    public const DEFAULT_VIRTUAL_HOST = '/';
    public const DEFAULT_PASSWORD = 'guest';
    public const DEFAULT_HEARTBEAT_INTERVAL = 0;
    public const DEFAULT_READ_TIMEOUT = 0.0;
    public const DEFAULT_USER = 'guest';
    public const DEFAULT_HOST = 'localhost';
    public const DEFAULT_RPC_TIMEOUT = 0.0;
    public const DEFAULT_WRITE_TIMEOUT = 0.0;
}
