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

namespace Asmblah\PhpAmqpCompat;

use Asmblah\PhpAmqpCompat\Configuration\DefaultConfiguration;
use Asmblah\PhpCodeShift\ShiftPackageInterface;
use InvalidArgumentException;
use Nytris\Core\Package\PackageContextInterface;
use Nytris\Core\Package\PackageInterface;

/**
 * Class AmqpCompat.
 *
 * Defines the public facade API for the library.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqpCompat implements AmqpCompatInterface
{
    private static bool $installed = false;

    /**
     * @inheritDoc
     */
    public static function getName(): string
    {
        return 'php-amqp-compat';
    }

    /**
     * @inheritDoc
     */
    public static function getVendor(): string
    {
        return 'asmblah';
    }

    /**
     * @inheritDoc
     */
    public static function install(PackageContextInterface $packageContext, PackageInterface $package): void
    {
        if (!$package instanceof AmqpCompatPackageInterface) {
            throw new InvalidArgumentException(
                sprintf(
                    'Package config must be a %s but it was a %s',
                    AmqpCompatPackageInterface::class,
                    $package::class
                )
            );
        }

        DefaultConfiguration::setDefaultSchedulerFactory($package->getSchedulerFactory());

        self::$installed = true;
    }

    /**
     * @inheritDoc
     */
    public static function isInstalled(): bool
    {
        return self::$installed;
    }

    /**
     * @inheritDoc
     */
    public static function uninstall(): void
    {
        self::$installed = false;
    }
}
