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
use Asmblah\PhpAmqpCompat\Exception\SocketConfigurationFailedException;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Exception;

/**
 * Class SocketConfigurationFailedExceptionTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class SocketConfigurationFailedExceptionTest extends AbstractTestCase
{
    public function testExceptionImplementsExceptionInterface(): void
    {
        $exception = new SocketConfigurationFailedException();

        static::assertInstanceOf(Exception::class, $exception);
        static::assertInstanceOf(ExceptionInterface::class, $exception);
    }

    public function testExceptionCanBeCreatedWithMessage(): void
    {
        $message = 'Socket configuration failed';
        $exception = new SocketConfigurationFailedException($message);

        static::assertSame($message, $exception->getMessage());
    }

    public function testExceptionCanBeCreatedWithMessageAndCode(): void
    {
        $message = 'Socket configuration failed';
        $code = 123;
        $exception = new SocketConfigurationFailedException($message, $code);

        static::assertSame($message, $exception->getMessage());
        static::assertSame($code, $exception->getCode());
    }
}
