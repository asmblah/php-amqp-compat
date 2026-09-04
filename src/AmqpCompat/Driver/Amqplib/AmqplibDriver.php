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

namespace Asmblah\PhpAmqpCompat\Driver\Amqplib;

use Asmblah\PhpAmqpCompat\Configuration\ConfigurationInterface;
use Asmblah\PhpAmqpCompat\Driver\DriverInterface;
use Asmblah\PhpAmqpCompat\Driver\ImplementationInterface;

/**
 * Class AmqplibDriver.
 *
 * Defines the driver-specific API.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqplibDriver implements DriverInterface
{
    /**
     * @inheritDoc
     */
    public function createImplementation(ConfigurationInterface $configuration): ImplementationInterface
    {
        return new AmqplibImplementation($configuration);
    }
}
