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

use Asmblah\PhpAmqpCompat\Configuration\ConfigurationInterface;

/**
 * Interface DriverInterface.
 *
 * Defines the driver-specific API.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface DriverInterface
{
    /**
     * Creates the implementation of the driver-specific API for the given configuration.
     */
    public function createImplementation(ConfigurationInterface $configuration): ImplementationInterface;
}
