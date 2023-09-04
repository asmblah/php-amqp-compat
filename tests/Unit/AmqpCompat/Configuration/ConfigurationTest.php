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

use Asmblah\PhpAmqpCompat\Configuration\Configuration;
use Asmblah\PhpAmqpCompat\Error\ErrorReporter;
use Asmblah\PhpAmqpCompat\Error\ErrorReporterInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class ConfigurationTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ConfigurationTest extends AbstractTestCase
{
    public function testGetErrorReporterReturnsTheProvidedErrorReporter(): void
    {
        $errorReporter = mock(ErrorReporterInterface::class);
        $configuration = new Configuration(null, $errorReporter);

        static::assertSame($errorReporter, $configuration->getErrorReporter());
    }

    public function testGetErrorReporterReturnsADefaultErrorReporterByDefault(): void
    {
        $configuration = new Configuration();

        static::assertInstanceOf(ErrorReporter::class, $configuration->getErrorReporter());
    }

    public function testGetErrorReporterReturnsTheSameDefaultErrorReporterOnSubsequentCallsByDefault(): void
    {
        $configuration = new Configuration();

        static::assertSame($configuration->getErrorReporter(), $configuration->getErrorReporter());
    }

    public function testGetLoggerReturnsTheProvidedLogger(): void
    {
        $logger = mock(LoggerInterface::class);
        $configuration = new Configuration($logger);

        static::assertSame($logger, $configuration->getLogger());
    }

    public function testGetLoggerReturnsANullLoggerByDefault(): void
    {
        $configuration = new Configuration();

        static::assertInstanceOf(NullLogger::class, $configuration->getLogger());
    }

    public function testGetLoggerReturnsTheSameNullLoggerOnSubsequentCallsByDefault(): void
    {
        $configuration = new Configuration();

        static::assertSame($configuration->getLogger(), $configuration->getLogger());
    }

    public function testGetUnlimitedTimeoutReturnsTheProvidedUnlimitedTimeout(): void
    {
        $configuration = new Configuration(null, null, 123.456);

        static::assertSame(123.456, $configuration->getUnlimitedTimeout());
    }

    public function testGetUnlimitedTimeoutReturnsTheDefaultUnlimitedTimeoutByDefault(): void
    {
        $configuration = new Configuration();

        static::assertSame(Configuration::DEFAULT_UNLIMITED_TIMEOUT, $configuration->getUnlimitedTimeout());
    }
}
