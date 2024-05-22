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

namespace Asmblah\PhpAmqpCompat\Driver\Common\Transport;

use Asmblah\PhpAmqpCompat\Connection\Config\ConnectionConfigInterface;

/**
 * Interface TransportConnectorInterface.
 *
 * Connects via the underlying php-amqplib, encapsulating the connection in a Transport.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface TransportConnectorInterface
{
    /**
     * Connects and returns a new Transport for the underlying driver.
     */
    public function connect(ConnectionConfigInterface $config): TransportInterface;
}
