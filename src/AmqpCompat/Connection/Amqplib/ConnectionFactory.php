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

namespace Asmblah\PhpAmqpCompat\Connection\Amqplib;

use PhpAmqpLib\Connection\AbstractConnection as AmqplibConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * Class ConnectionFactory.
 *
 * Opens the underlying connection to the AMQP broker via php-amqplib.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ConnectionFactory implements ConnectionFactoryInterface
{
    /**
     * @inheritDoc
     */
    public function connect(
        string $host,
        int $port,
        string $user,
        string $password,
        string $virtualHost,
        bool $insist,
        string $loginMethod,
        string $locale,
        float $connectionTimeout,
        float $readWriteTimeout,
        bool $keepAlive,
        int $heartbeatInterval,
        float $rpcTimeout
    ): AmqplibConnection {
        return new AMQPStreamConnection(
            $host,
            $port,
            $user,
            $password,
            $virtualHost,
            $insist,
            $loginMethod,
            null,
            $locale,
            $connectionTimeout,
            $readWriteTimeout,
            null,
            $keepAlive,
            $heartbeatInterval,
            $rpcTimeout
        );
    }
}
