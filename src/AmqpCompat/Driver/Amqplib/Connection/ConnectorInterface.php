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

namespace Asmblah\PhpAmqpCompat\Driver\Amqplib\Connection;

use AMQPConnectionException;
use Asmblah\PhpAmqpCompat\Connection\Config\ConnectionConfigInterface;
use PhpAmqpLib\Connection\AbstractConnection as AmqplibConnection;

/**
 * Interface ConnectorInterface.
 *
 * Performs the connection to the AMQP broker.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface ConnectorInterface
{
    /**
     * Performs the connection to the AMQP broker.
     *
     * @throws AMQPConnectionException On connection failure.
     */
    public function connect(ConnectionConfigInterface $config): AmqplibConnection;
}
