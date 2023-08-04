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

namespace Asmblah\PhpAmqpCompat\Configuration;

use Asmblah\PhpAmqpCompat\Error\ErrorReporter;
use Asmblah\PhpAmqpCompat\Error\ErrorReporterInterface;
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
    // Use 30 minutes as the default "unlimited" timeout.
    public const DEFAULT_UNLIMITED_TIMEOUT = 1800.0;

    private ErrorReporterInterface $errorReporter;
    private LoggerInterface $logger;
    private float $unlimitedTimeout;

    public function __construct(
        ?LoggerInterface $logger = null,
        ?ErrorReporterInterface $errorReporter = null,
        ?float $unlimitedTimeout = null
    ) {
        $this->errorReporter = $errorReporter ?? new ErrorReporter();
        $this->logger = $logger ?? new NullLogger();

        $this->unlimitedTimeout = $unlimitedTimeout ?? self::DEFAULT_UNLIMITED_TIMEOUT;
    }

    /**
     * @inheritDoc
     */
    public function getErrorReporter(): ErrorReporterInterface
    {
        return $this->errorReporter;
    }

    /**
     * @inheritDoc
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @inheritDoc
     */
    public function getUnlimitedTimeout(): float
    {
        return $this->unlimitedTimeout;
    }
}
