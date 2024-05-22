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

namespace Asmblah\PhpAmqpCompat\Driver\Amqplib\Transport;

use Asmblah\PhpAmqpCompat\Connection\Config\ConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Connection\ConnectorInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Transport\TransportConnectorInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Transport\TransportInterface;
use Asmblah\PhpAmqpCompat\Socket\SocketSubsystemInterface;

/**
 * Class TransportConnector.
 *
 * Connects via the underlying php-amqplib, encapsulating the connection in a Transport.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class TransportConnector implements TransportConnectorInterface
{
    public function __construct(
        private readonly ConnectorInterface $connector,
        private readonly SocketSubsystemInterface $socketSubsystem
    ) {
    }

    /**
     * @inheritDoc
     */
    public function connect(ConnectionConfigInterface $config): TransportInterface
    {
        // Open the underlying connection to the AMQP broker via php-amqplib.
        $amqplibConnection = $this->connector->connect($config);

        return new Transport($amqplibConnection, $this->socketSubsystem);
    }
}
