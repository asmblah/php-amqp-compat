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

namespace Asmblah\PhpAmqpCompat\Driver;

use Asmblah\PhpAmqpCompat\Driver\Common\Exception\ExceptionHandlerInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Heartbeat\HeartbeatTransmitterInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Logger\LoggerInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Transport\TransportConnectorInterface;

/**
 * Interface ImplementationInterface.
 *
 * Defines the driver-specific API.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface ImplementationInterface
{
    /**
     * Fetches the driver's ExceptionHandler implementation.
     */
    public function getExceptionHandler(): ExceptionHandlerInterface;

    /**
     * Fetches the driver's HeartbeatTransmitter implementation.
     */
    public function getHeartbeatTransmitter(): HeartbeatTransmitterInterface;

    /**
     * Fetches the driver's Logger implementation.
     */
    public function getLogger(): LoggerInterface;

    /**
     * Fetches the driver's TransportConnector implementation.
     */
    public function getTransportConnector(): TransportConnectorInterface;
}
