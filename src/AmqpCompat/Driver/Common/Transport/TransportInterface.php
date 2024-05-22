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

use Asmblah\PhpAmqpCompat\Exception\TransportConfigurationFailedException;

/**
 * Interface TransportInterface.
 *
 * Manages the connection with the underlying driver.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface TransportInterface
{
    /**
     * Updates the read timeout for the connection.
     * Will reconfigure the open connection if one is already established.
     *
     * @throws TransportConfigurationFailedException If the socket read timeout change fails.
     */
    public function setReadTimeout(float $seconds): void;
}
