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

namespace Asmblah\PhpAmqpCompat\Connection;

use PhpAmqpLib\Connection\AbstractConnection as AmqplibConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * Class Connector.
 *
 * Default implementation that connects using php-amqplib's AMQPStreamConnection.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class Connector implements ConnectorInterface
{
    /**
     * @inheritDoc
     */
    public function connect(ConnectionConfigInterface $config): AmqplibConnection
    {
        return new AMQPStreamConnection(
            $config->getHost(),
            $config->getPort(),
            $config->getUser(),
            $config->getPassword(),
            $config->getVirtualHost(),
            false,
            'AMQPLAIN',
            null,
            'en_US',
            $config->getConnectionTimeout(),
            $config->getReadTimeout(),
            null,
            false,
            $config->getHeartbeatInterval(),
            $config->getRpcTimeout()
        );
    }
}
