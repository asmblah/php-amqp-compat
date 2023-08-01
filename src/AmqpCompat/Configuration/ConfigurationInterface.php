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

namespace Asmblah\PhpAmqpCompat\Configuration;

use Psr\Log\LoggerInterface;

/**
 * Interface ConfigurationInterface.
 *
 * May be implemented by a custom class and set on the AmqpManager to allow extension.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface ConfigurationInterface
{
    /**
     * Fetches a logger to use for additional/internal logging by this library.
     */
    public function getLogger(): LoggerInterface;
}
