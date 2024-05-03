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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\Tests\Functional\Reference\Util\ClassEmulator;

use AMQPDecimal;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Asmblah\PhpAmqpCompat\Tests\Functional\Reference\Util\ClassEmulator\AmqpDecimalEmulator;
use Asmblah\PhpAmqpCompat\Tests\Functional\Reference\Util\ClassEmulator\DelegatingClassEmulatorInterface;
use Mockery\MockInterface;

/**
 * Class AmqpDecimalEmulatorTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqpDecimalEmulatorTest extends AbstractTestCase
{
    private MockInterface&DelegatingClassEmulatorInterface $delegatingEmulator;
    private AmqpDecimalEmulator $emulator;

    public function setUp(): void
    {
        $this->delegatingEmulator = mock(DelegatingClassEmulatorInterface::class);

        $this->delegatingEmulator->allows('dump')
            ->andReturnUsing(function (mixed $value, int $depth) {
                return '[dump depth=' . $depth . ']' . var_export($value, true) . '[/dump]';
            })
            ->byDefault();

        $this->emulator = new AmqpDecimalEmulator();
    }

    public function testDumpDumpsCorrectlyWithDepthOfZero(): void
    {
        $amqpDecimal = new AMQPDecimal(3, 2);
        $expectedOutput = <<<EOS
object(AMQPDecimal)#123 (2) {
  ["exponent":"AMQPDecimal":private]=>
  [dump depth=1]3[/dump]
  ["significand":"AMQPDecimal":private]=>
  [dump depth=1]2[/dump]
}
EOS;

        static::assertSame(
            $expectedOutput,
            $this->emulator->dump($amqpDecimal, 0, 123, $this->delegatingEmulator)
        );
    }

    public function testDumpDumpsCorrectlyWithDepthGreaterThanZero(): void
    {
        $amqpDecimal = new AMQPDecimal(3, 2);
        $expectedOutput = <<<EOS
object(AMQPDecimal)#567 (2) {
  ["exponent":"AMQPDecimal":private]=>
  [dump depth=5]3[/dump]
  ["significand":"AMQPDecimal":private]=>
  [dump depth=5]2[/dump]
}
EOS;

        static::assertSame(
            $expectedOutput,
            $this->emulator->dump($amqpDecimal, 4, 567, $this->delegatingEmulator)
        );
    }

    public function testGetClassNameReturnsCorrectClass(): void
    {
        static::assertSame(AMQPDecimal::class, $this->emulator->getClassName());
    }
}
