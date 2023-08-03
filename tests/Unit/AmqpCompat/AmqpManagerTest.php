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

use Asmblah\PhpAmqpCompat\AmqpManager;
use Asmblah\PhpAmqpCompat\Configuration\ConfigurationInterface;
use Asmblah\PhpAmqpCompat\Integration\AmqpIntegration;
use Asmblah\PhpAmqpCompat\Integration\AmqpIntegrationInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use LogicException;

/**
 * Class AmqpManagerTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqpManagerTest extends AbstractTestCase
{
    public function setUp(): void
    {
        AmqpManager::setAmqpIntegration(null);
        AmqpManager::setConfiguration(null);
    }

    public function tearDown(): void
    {
        AmqpManager::setAmqpIntegration(null);
        AmqpManager::setConfiguration(null);
    }

    public function testGetAmqpIntegrationFetchesDefaultImplementationByDefault(): void
    {
        $amqpIntegration = AmqpManager::getAmqpIntegration();

        static::assertInstanceOf(AmqpIntegration::class, $amqpIntegration);
    }

    public function testGetAmqpIntegrationReturnsSameInstanceOnSubsequentCalls(): void
    {
        static::assertSame(AmqpManager::getAmqpIntegration(), AmqpManager::getAmqpIntegration());
    }

    public function testGetConfigurationReturnsDefaultConfigurationIfNotOverridden(): void
    {
        static::assertInstanceOf(ConfigurationInterface::class, AmqpManager::getConfiguration());
    }

    public function testSetAmqpIntegrationSetsSpecifiedIntegration(): void
    {
        $amqpIntegration = mock(AmqpIntegrationInterface::class);

        AmqpManager::setAmqpIntegration($amqpIntegration);

        static::assertSame($amqpIntegration, AmqpManager::getAmqpIntegration());
    }

    public function testSetConfigurationSetsSpecifiedConfiguration(): void
    {
        $configuration = mock(ConfigurationInterface::class);

        AmqpManager::setConfiguration($configuration);

        static::assertSame($configuration, AmqpManager::getConfiguration());
    }

    public function testSetConfigurationThrowsWhenIntegrationHasAlreadyBeenFetchedImplicitly(): void
    {
        AmqpManager::getAmqpIntegration();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot set configuration while an AmqpIntegration has already been set');

        AmqpManager::setConfiguration(mock(ConfigurationInterface::class));
    }

    public function testSetConfigurationThrowsWhenIntegrationHasAlreadyBeenSetExplicitly(): void
    {
        $amqpIntegration = mock(AmqpIntegrationInterface::class);
        AmqpManager::setAmqpIntegration($amqpIntegration);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot set configuration while an AmqpIntegration has already been set');

        AmqpManager::setConfiguration(mock(ConfigurationInterface::class));
    }
}
