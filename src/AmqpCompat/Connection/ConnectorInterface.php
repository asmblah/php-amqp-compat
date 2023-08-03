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

use Asmblah\PhpAmqpCompat\Connection\Config\ConnectionConfigInterface;
use Exception;
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
     * @throws Exception On connection failure.
     */
    public function connect(ConnectionConfigInterface $config): AmqplibConnection;
}
