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
use Asmblah\PhpAmqpCompat\Exception\TooManyChannelsOnConnectionException;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Exception;

/**
 * Class TooManyChannelsOnConnectionExceptionTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class TooManyChannelsOnConnectionExceptionTest extends AbstractTestCase
{
    public function testExceptionImplementsExceptionInterface(): void
    {
        $exception = new TooManyChannelsOnConnectionException();

        static::assertInstanceOf(Exception::class, $exception);
        static::assertInstanceOf(ExceptionInterface::class, $exception);
    }

    public function testExceptionCanBeCreatedWithMessage(): void
    {
        $message = 'Too many channels on connection';
        $exception = new TooManyChannelsOnConnectionException($message);

        static::assertSame($message, $exception->getMessage());
    }
}
