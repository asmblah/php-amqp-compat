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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\AmqpCompat\Exception;

use Asmblah\PhpAmqpCompat\Exception\ExceptionInterface;
use Asmblah\PhpAmqpCompat\Exception\TransportConfigurationFailedException;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Exception;

/**
 * Class TransportConfigurationFailedExceptionTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class TransportConfigurationFailedExceptionTest extends AbstractTestCase
{
    public function testExceptionImplementsExceptionInterface(): void
    {
        $exception = new TransportConfigurationFailedException();

        static::assertInstanceOf(Exception::class, $exception);
        static::assertInstanceOf(ExceptionInterface::class, $exception);
    }

    public function testExceptionCanBeCreatedWithMessage(): void
    {
        $message = 'Transport configuration failed';
        $exception = new TransportConfigurationFailedException($message);

        static::assertSame($message, $exception->getMessage());
    }
}
