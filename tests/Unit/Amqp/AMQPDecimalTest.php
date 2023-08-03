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

use AMQPDecimal;
use AMQPValueException;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;

/**
 * Class AMQPDecimalTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AMQPDecimalTest extends AbstractTestCase
{
    public function testConstructorThrowsWhenExponentIsNegative(): void
    {
        $this->expectException(AMQPValueException::class);
        $this->expectExceptionMessage('Decimal exponent value must be unsigned.');

        new AMQPDecimal(-1, 1234);
    }

    public function testConstructorThrowsWhenExponentIsTooLarge(): void
    {
        $this->expectException(AMQPValueException::class);
        $this->expectExceptionMessage('Decimal exponent value must be less than 255.');

        new AMQPDecimal(AMQPDecimal::EXPONENT_MAX + 1, 1234);
    }

    public function testConstructorThrowsWhenSignificandIsNegative(): void
    {
        $this->expectException(AMQPValueException::class);
        $this->expectExceptionMessage('Decimal significand value must be unsigned.');

        new AMQPDecimal(0, -1);
    }

    public function testConstructorThrowsWhenSignificandIsTooLarge(): void
    {
        $this->expectException(AMQPValueException::class);
        $this->expectExceptionMessage('Decimal significand value must be less than 4294967295.');

        new AMQPDecimal(0, AMQPDecimal::SIGNIFICAND_MAX + 1);
    }

    public function testGetExponent(): void
    {
        $amqpDecimal = new AMQPDecimal(2, 1234);

        static::assertSame(2, $amqpDecimal->getExponent());
    }

    public function testGetSignificand(): void
    {
        $amqpDecimal = new AMQPDecimal(0, 1234);

        static::assertSame(1234, $amqpDecimal->getSignificand());
    }
}
