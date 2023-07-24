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

use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Connection\ConnectionConfigInterface;
use Exception;

interface AmqpFactoryInterface
{
    /**
     * Connects to the AMQP server.
     *
     * @throws Exception
     */
    public function connect(ConnectionConfigInterface $config): AmqpConnectionBridgeInterface;

    /**
     * Creates a configuration for later connection.
     */
    public function createConnectionConfig(array $credentials): ConnectionConfigInterface;
}
