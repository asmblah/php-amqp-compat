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
        DefaultConfiguration::initialise();
    }

    public function tearDown(): void
    {
        DefaultConfiguration::uninitialise();
        DefaultConfiguration::initialise();
    }

    public function testNullDefaultSchedulerFactoryIsUsedInitially(): void
    {
        static::assertInstanceOf(NullSchedulerFactory::class, DefaultConfiguration::getDefaultSchedulerFactory());
    }

    public function testDefaultSchedulerFactoryCanBeOverridden(): void
    {
        $schedulerFactory = mock(SchedulerFactoryInterface::class);
        DefaultConfiguration::setDefaultSchedulerFactory($schedulerFactory);

        static::assertSame($schedulerFactory, DefaultConfiguration::getDefaultSchedulerFactory());
    }
}
