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

use Asmblah\PhpAmqpCompat\Driver\DriverInterface;
use Asmblah\PhpAmqpCompat\Driver\ImplementationInterface;
use Asmblah\PhpAmqpCompat\Error\ErrorReporter;
use Asmblah\PhpAmqpCompat\Error\ErrorReporterInterface;
use Asmblah\PhpAmqpCompat\Scheduler\Factory\SchedulerFactoryInterface;
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

    private readonly ImplementationInterface $driverImplementation;
    private readonly ErrorReporterInterface $errorReporter;
    private readonly LoggerInterface $logger;
    private readonly SchedulerFactoryInterface $schedulerFactory;
    private readonly float $unlimitedTimeout;

    public function __construct(
        ?LoggerInterface $logger = null,
        ?ErrorReporterInterface $errorReporter = null,
        ?float $unlimitedTimeout = null,
        ?SchedulerFactoryInterface $schedulerFactory = null,
        ?DriverInterface $driver = null
    ) {
        $this->errorReporter = $errorReporter ?? new ErrorReporter();
        $this->logger = $logger ?? new NullLogger();
        $this->schedulerFactory = $schedulerFactory ?? DefaultConfiguration::getDefaultSchedulerFactory();

        $this->unlimitedTimeout = $unlimitedTimeout ?? self::DEFAULT_UNLIMITED_TIMEOUT;

        $driver = $driver ?? DefaultConfiguration::getDefaultDriver();
        $this->driverImplementation = $driver->createImplementation($this);
    }

    /**
     * @inheritDoc
     */
    public function getDriverImplementation(): ImplementationInterface
    {
        return $this->driverImplementation;
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
    public function getSchedulerFactory(): SchedulerFactoryInterface
    {
        return $this->schedulerFactory;
    }

    /**
     * @inheritDoc
     */
    public function getUnlimitedTimeout(): float
    {
        return $this->unlimitedTimeout;
    }
}
