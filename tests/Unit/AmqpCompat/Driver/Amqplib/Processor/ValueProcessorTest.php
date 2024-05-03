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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\AmqpCompat\Driver\Amqplib\Processor;

use AMQPDecimal;
use AMQPTimestamp;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Processor\ValueProcessor;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use DateTime;
use PhpAmqpLib\Wire\AMQPDecimal as AmqplibDecimal;

/**
 * Class ValueProcessorTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ValueProcessorTest extends AbstractTestCase
{
    private ValueProcessor $processor;

    public function setUp(): void
    {
        $this->processor = new ValueProcessor();
    }

    public function testProcessValueForDriverConvertsAmqpDecimalToAmqplibDecimal(): void
    {
        $amqplibDecimal = $this->processor->processValueForDriver(new AMQPDecimal(3, 2));

        static::assertInstanceOf(AmqplibDecimal::class, $amqplibDecimal);
        static::assertSame(2, $amqplibDecimal->getN());
        static::assertSame(3, $amqplibDecimal->getE());
    }

    public function testProcessValueForDriverConvertsAmqpTimestampToDateTime(): void
    {
        $dateTime = $this->processor->processValueForDriver(new AMQPTimestamp(12345678));

        static::assertInstanceOf(DateTime::class, $dateTime);
        static::assertSame(12345678, $dateTime->getTimestamp());
    }

    public function testProcessValueForDriverConvertsAmqpDecimalNestedArrayValueToAmqplibDecimal(): void
    {
        $result = $this->processor->processValueForDriver([
            'my' => [
                'stuff' => [
                    'my-decimal' => new AMQPDecimal(3, 2),
                ],
            ],
        ]);

        $amqplibDecimal = $result['my']['stuff']['my-decimal'];
        static::assertInstanceOf(AmqplibDecimal::class, $amqplibDecimal);
        static::assertSame(2, $amqplibDecimal->getN());
        static::assertSame(3, $amqplibDecimal->getE());
    }

    public function testProcessValueForDriverConvertsAmqpTimestampNestedArrayValueToDateTime(): void
    {
        $result = $this->processor->processValueForDriver([
            'my' => [
                'stuff' => [
                    'my-timestamp' => new AMQPTimestamp(12345678),
                ],
            ],
        ]);

        $dateTime = $result['my']['stuff']['my-timestamp'];
        static::assertInstanceOf(DateTime::class, $dateTime);
        static::assertSame(12345678, $dateTime->getTimestamp());
    }

    public function testProcessValueFromDriverConvertsAmqplibDecimalToAmqpDecimal(): void
    {
        $amqpDecimal = $this->processor->processValueFromDriver(new AmqplibDecimal(2, 3));

        static::assertInstanceOf(AMQPDecimal::class, $amqpDecimal);
        static::assertSame(2, $amqpDecimal->getSignificand());
        static::assertSame(3, $amqpDecimal->getExponent());
    }

    public function testProcessValueFromDriverConvertsDateTimeToAmqpTimestamp(): void
    {
        $amqpTimestamp = $this->processor->processValueFromDriver(DateTime::createFromFormat('U', '12345678'));

        static::assertInstanceOf(AMQPTimestamp::class, $amqpTimestamp);
        static::assertSame('12345678', $amqpTimestamp->getTimestamp());
    }

    public function testProcessValueFromDriverConvertsAmqplibDecimalNestedArrayValueToAmqpDecimal(): void
    {
        $result = $this->processor->processValueFromDriver([
            'my' => [
                'stuff' => [
                    'my-decimal' => new AmqplibDecimal(2, 3),
                ],
            ],
        ]);

        $amqpDecimal = $result['my']['stuff']['my-decimal'];
        static::assertInstanceOf(AMQPDecimal::class, $amqpDecimal);
        static::assertSame(2, $amqpDecimal->getSignificand());
        static::assertSame(3, $amqpDecimal->getExponent());
    }

    public function testProcessValueFromDriverConvertsDateTimeNestedArrayValueToAmqpTimestamp(): void
    {
        $result = $this->processor->processValueFromDriver([
            'my' => [
                'stuff' => [
                    'my-timestamp' => DateTime::createFromFormat('U', '12345678'),
                ],
            ],
        ]);

        $amqpTimestamp = $result['my']['stuff']['my-timestamp'];
        static::assertInstanceOf(AMQPTimestamp::class, $amqpTimestamp);
        static::assertSame('12345678', $amqpTimestamp->getTimestamp());
    }
}
