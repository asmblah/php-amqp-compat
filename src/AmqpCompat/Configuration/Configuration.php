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
use Psr\Log\NullLogger;

/**
 * Class Configuration.
 *
 * Default implementation that defaults to using a NullLogger for internal logging.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class Configuration implements ConfigurationInterface
{
    private LoggerInterface $logger;

    public function __construct(
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @inheritDoc
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
}
