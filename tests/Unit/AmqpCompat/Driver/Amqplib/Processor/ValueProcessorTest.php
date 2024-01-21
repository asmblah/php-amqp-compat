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

use AMQPTimestamp;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Processor\ValueProcessor;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use DateTime;

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

    public function testProcessValueForDriverConvertsAmqpTimestampToDateTime(): void
    {
        $dateTime = $this->processor->processValueForDriver(new AMQPTimestamp(12345678));

        static::assertInstanceOf(DateTime::class, $dateTime);
        static::assertSame(12345678, $dateTime->getTimestamp());
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

    public function testProcessValueFromDriverConvertsDateTimeToAmqpTimestamp(): void
    {
        $amqpTimestamp = $this->processor->processValueFromDriver(DateTime::createFromFormat('U', '12345678'));

        static::assertInstanceOf(AMQPTimestamp::class, $amqpTimestamp);
        static::assertSame('12345678', $amqpTimestamp->getTimestamp());
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
