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
use Asmblah\PhpAmqpCompat\AmqpCompatPackage;
use Asmblah\PhpAmqpCompat\Scheduler\Factory\NullSchedulerFactory;
use Asmblah\PhpAmqpCompat\Scheduler\Factory\SchedulerFactoryInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;

/**
 * Class AmqpCompatPackageTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqpCompatPackageTest extends AbstractTestCase
{
    public function testNullSchedulerFactoryIsUsedByDefault(): void
    {
        $package = new AmqpCompatPackage();

        static::assertInstanceOf(NullSchedulerFactory::class, $package->getSchedulerFactory());
    }

    public function testACustomSchedulerFactoryMayBeSpecified(): void
    {
        $schedulerFactory = mock(SchedulerFactoryInterface::class);
        $package = new AmqpCompatPackage(schedulerFactory: $schedulerFactory);

        static::assertSame($schedulerFactory, $package->getSchedulerFactory());
    }

    public function testCorrectPackageFacadeFqcnIsUsed(): void
    {
        $package = new AmqpCompatPackage();

        static::assertSame(AmqpCompat::class, $package->getPackageFacadeFqcn());
    }
}
