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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\AmqpCompat\Configuration;

use Asmblah\PhpAmqpCompat\Configuration\DefaultConfiguration;
use Asmblah\PhpAmqpCompat\Scheduler\Factory\NullSchedulerFactory;
use Asmblah\PhpAmqpCompat\Scheduler\Factory\SchedulerFactoryInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use LogicException;

/**
 * Class DefaultConfigurationTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class DefaultConfigurationTest extends AbstractTestCase
{
    public function setUp(): void
    {
        DefaultConfiguration::uninitialise();
    }

    public function tearDown(): void
    {
        DefaultConfiguration::uninitialise();
    }

    public function testGetDefaultSchedulerFactoryReturnsNullFactoryByDefault(): void
    {
        DefaultConfiguration::initialise();

        static::assertInstanceOf(NullSchedulerFactory::class, DefaultConfiguration::getDefaultSchedulerFactory());
    }

    public function testGetDefaultSchedulerFactoryRaisesExceptionWhenNotYetInitialised(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('DefaultConfiguration has not been initialised');

        DefaultConfiguration::getDefaultSchedulerFactory();
    }

    // When installed as a Nytris package, `AmqpCompat::install(...)` will override
    // before this library's `bootstrap.php` is run and calls ::initialise(...).
    public function testInitialiseDoesNotChangeDefaultSchedulerFactoryIfAlreadyOverridden(): void
    {
        $customSchedulerFactory = mock(SchedulerFactoryInterface::class);
        DefaultConfiguration::setDefaultSchedulerFactory($customSchedulerFactory);

        DefaultConfiguration::initialise();

        static::assertSame($customSchedulerFactory, DefaultConfiguration::getDefaultSchedulerFactory());
    }

    public function testIsInitialisedReturnsFalseInitially(): void
    {
        static::assertFalse(DefaultConfiguration::isInitialised());
    }

    public function testIsInitialisedReturnsTrueAfterInitialisation(): void
    {
        DefaultConfiguration::initialise();

        static::assertTrue(DefaultConfiguration::isInitialised());
    }

    public function testIsInitialisedReturnsFalseAfterLaterUninitialisation(): void
    {
        DefaultConfiguration::initialise();
        DefaultConfiguration::uninitialise();

        static::assertFalse(DefaultConfiguration::isInitialised());
    }

    public function testSetDefaultSchedulerFactoryOverridesTheSetFactory(): void
    {
        DefaultConfiguration::initialise();
        $schedulerFactory = mock(SchedulerFactoryInterface::class);

        DefaultConfiguration::setDefaultSchedulerFactory($schedulerFactory);

        static::assertSame($schedulerFactory, DefaultConfiguration::getDefaultSchedulerFactory());
    }
}
