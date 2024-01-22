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

namespace Asmblah\PhpAmqpCompat\Driver\Common\Processor;

/**
 * Interface ValueProcessorInterface.
 *
 * Transforms AMQP values either originating from or destined for the driver.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface ValueProcessorInterface
{
    /**
     * Processes the given value that will be passed to the driver.
     */
    public function processValueForDriver(mixed $value): mixed;

    /**
     * Processes the given value originating from the driver.
     */
    public function processValueFromDriver(mixed $value): mixed;
}
