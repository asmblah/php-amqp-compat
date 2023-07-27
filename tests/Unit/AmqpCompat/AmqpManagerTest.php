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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\AmqpCompat;

use Asmblah\PhpAmqpCompat\AmqpFactory;
use Asmblah\PhpAmqpCompat\AmqpFactoryInterface;
use Asmblah\PhpAmqpCompat\AmqpManager;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;

/**
 * Class AmqpManagerTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqpManagerTest extends AbstractTestCase
{
    public function setUp(): void
    {
        AmqpManager::setAmqpFactory(null);
    }

    public function tearDown(): void
    {
        AmqpManager::setAmqpFactory(null);
    }

    public function testGetAmqpFactoryFetchesDefaultImplementationByDefault(): void
    {
        $amqpFactory = AmqpManager::getAmqpFactory();

        static::assertInstanceOf(AmqpFactory::class, $amqpFactory);
    }

    public function testGetAmqpFactoryReturnsSameInstanceOnSubsequentCalls(): void
    {
        static::assertSame(AmqpManager::getAmqpFactory(), AmqpManager::getAmqpFactory());
    }

    public function testSetAmqpFactorySetsSpecifiedFactory(): void
    {
        $amqpFactory = mock(AmqpFactoryInterface::class);
        AmqpManager::setAmqpFactory($amqpFactory);

        static::assertSame($amqpFactory, AmqpManager::getAmqpFactory());
    }
}
