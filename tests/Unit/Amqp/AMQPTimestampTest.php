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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\Amqp;

use AMQPTimestamp;
use AMQPValueException;
use ArgumentCountError;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use TypeError;

/**
 * Class AMQPTimestampTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AMQPTimestampTest extends AbstractTestCase
{
    public function testConstructorRaisesExceptionWhenTimestampTooSmall(): void
    {
        $this->expectException(AMQPValueException::class);
        $this->expectExceptionMessage('The timestamp parameter must be greater than 0.');

        new AMQPTimestamp(-1);
    }

    public function testConstructorRaisesExceptionWhenTimestampTooLarge(): void
    {
        $this->expectException(AMQPValueException::class);
        $this->expectExceptionMessage('The timestamp parameter must be less than 18446744073709551616.');

        new AMQPTimestamp(INF);
    }

    // Note that this is unlike the error message suggests.
    public function testConstructorDoesNotRaiseExceptionWhenTimestampIsExactlyAtMax(): void
    {
        $this->expectNotToPerformAssertions();

        new AMQPTimestamp((float) AMQPTimestamp::MAX);
    }

    // This test is required due to the special reimplementation logic in AMQPTimestamp.
    public function testConstructorRaisesArgumentCountErrorWhenExpected(): void
    {
        $this->expectException(ArgumentCountError::class);
        $this->expectExceptionMessage('AMQPTimestamp::__construct() expects exactly 1 argument, 0 given');

        new AMQPTimestamp(/* Missing timestamp. */);
    }

    // This test is required due to the special reimplementation logic in AMQPTimestamp.
    public function testConstructorRaisesTypeErrorWhenExpected(): void
    {
        $this->expectException(TypeError::class);
        $this->expectExceptionMessage(
            'AMQPTimestamp::__construct(): Argument #1 ($timestamp) must be of type float, boolean given'
        );

        new AMQPTimestamp(true /* Boolean is invalid. */);
    }

    public function testGetTimestampReturnsTheTimestampWhenGivenAsInteger(): void
    {
        $timestamp = new AMQPTimestamp(123456789);

        static::assertSame('123456789', $timestamp->getTimestamp());
    }

    public function testGetTimestampReturnsTheTimestampWhenGivenAsFloat(): void
    {
        $timestamp = new AMQPTimestamp(123456789.123);

        static::assertSame('123456789', $timestamp->getTimestamp(), 'Should be truncated');
    }

    public function testMagicToStringReturnsTheTimestampWhenGivenAsInteger(): void
    {
        $timestamp = new AMQPTimestamp(123456789);

        static::assertSame('123456789', $timestamp->__toString());
    }

    public function testMagicToStringReturnsTheTimestampWhenGivenAsFloat(): void
    {
        $timestamp = new AMQPTimestamp(123456789.123);

        static::assertSame('123456789', $timestamp->__toString(), 'Should be truncated');
    }
}
