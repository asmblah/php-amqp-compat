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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\AmqpCompat;

use Asmblah\PhpAmqpCompat\AmqpCompat;
use Asmblah\PhpAmqpCompat\AmqpCompatPackageInterface;
use Asmblah\PhpAmqpCompat\Configuration\DefaultConfiguration;
use Asmblah\PhpAmqpCompat\Driver\DriverInterface;
use Asmblah\PhpAmqpCompat\Scheduler\Factory\SchedulerFactoryInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Nytris\Core\Package\PackageContextInterface;

/**
 * Class AmqpCompatTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqpCompatTest extends AbstractTestCase
{
    public function setUp(): void
    {
        DefaultConfiguration::uninitialise();
        DefaultConfiguration::initialise();
        AmqpCompat::uninstall();
    }

    public function tearDown(): void
    {
        DefaultConfiguration::uninitialise();
        DefaultConfiguration::initialise();
        AmqpCompat::uninstall();
    }

    public function testInstallSetsDefaultDriver(): void
    {
        $packageContext = mock(PackageContextInterface::class);
        $driver = mock(DriverInterface::class);
        $package = mock(AmqpCompatPackageInterface::class, [
            'getDriver' => $driver,
            'getSchedulerFactory' => mock(SchedulerFactoryInterface::class),
        ]);

        AmqpCompat::install($packageContext, $package);

        static::assertSame($driver, DefaultConfiguration::getDefaultDriver());
    }

    public function testInstallSetsDefaultSchedulerFactory(): void
    {
        $packageContext = mock(PackageContextInterface::class);
        $schedulerFactory = mock(SchedulerFactoryInterface::class);
        $package = mock(AmqpCompatPackageInterface::class, [
            'getDriver' => mock(DriverInterface::class),
            'getSchedulerFactory' => $schedulerFactory,
        ]);

        AmqpCompat::install($packageContext, $package);

        static::assertSame($schedulerFactory, DefaultConfiguration::getDefaultSchedulerFactory());
    }

    public function testIsInstalledReturnsTrueWhenInstalled(): void
    {
        $packageContext = mock(PackageContextInterface::class);
        $package = mock(AmqpCompatPackageInterface::class, [
            'getDriver' => mock(DriverInterface::class),
            'getSchedulerFactory' => mock(SchedulerFactoryInterface::class),
        ]);

        AmqpCompat::install($packageContext, $package);

        static::assertTrue(AmqpCompat::isInstalled());
    }

    public function testIsInstalledReturnsFalseWhenNotInstalled(): void
    {
        static::assertFalse(AmqpCompat::isInstalled());
    }
}
