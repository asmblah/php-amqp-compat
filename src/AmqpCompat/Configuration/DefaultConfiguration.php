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

use Asmblah\PhpAmqpCompat\Scheduler\Factory\NullSchedulerFactory;
use Asmblah\PhpAmqpCompat\Scheduler\Factory\SchedulerFactoryInterface;
use LogicException;

/**
 * Class DefaultConfiguration.
 *
 * Defines the default configuration.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class DefaultConfiguration
{
    private static SchedulerFactoryInterface $defaultSchedulerFactory;
    private static bool $initialised = false;

    /**
     * Fetches the default scheduler factory to use.
     */
    public static function getDefaultSchedulerFactory(): SchedulerFactoryInterface
    {
        if (!self::$initialised) {
            throw new LogicException('DefaultConfiguration has not been initialised');
        }

        return self::$defaultSchedulerFactory;
    }

    /**
     * Initialises the default configuration.
     */
    public static function initialise(): void
    {
        if (self::$initialised) {
            return; // Already initialised.
        }

        self::$defaultSchedulerFactory = new NullSchedulerFactory();
        self::$initialised = true;
    }

    /**
     * Determines whether initialisation of the default configuration has been performed yet.
     */
    public static function isInitialised(): bool
    {
        return self::$initialised;
    }

    /**
     * Overrides the default scheduler factory.
     *
     * Used by AmqpCompat::install(...) when installed as a Nytris package.
     */
    public static function setDefaultSchedulerFactory(SchedulerFactoryInterface $schedulerFactory): void
    {
        self::initialise();

        self::$defaultSchedulerFactory = $schedulerFactory;
    }

    /**
     * Uninitialises the default configuration.
     */
    public static function uninitialise(): void
    {
        self::setDefaultSchedulerFactory(new NullSchedulerFactory());

        self::$initialised = false;
    }
}
