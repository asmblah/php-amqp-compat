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

use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Asmblah\PhpAmqpCompat\Tests\Functional\Reference\Util\ClassEmulator\ClassEmulatorInterface;
use Asmblah\PhpAmqpCompat\Tests\Functional\Reference\Util\ClassEmulator\DelegatingClassEmulator;
use Asmblah\PhpAmqpCompat\Tests\Functional\Reference\Util\ContextResolver;
use Generator;
use LogicException;
use Mockery\MockInterface;
use stdClass;

/**
 * Class DelegatingClassEmulatorTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class DelegatingClassEmulatorTest extends AbstractTestCase
{
    private MockInterface&ContextResolver $contextResolver;
    private DelegatingClassEmulator $emulator;

    public function setUp(): void
    {
        $this->contextResolver = mock(ContextResolver::class);
        $this->contextResolver->allows('getContext')
            ->andReturn(['file' => 'my_first_module.php'])
            ->byDefault();

        $this->emulator = new DelegatingClassEmulator($this->contextResolver, [
            'my_first_module.php' => [9998, 9994],
        ]);
        $this->emulator->setNativeGetClassMethods(
            static fn ($objectOrClassName) => get_class_methods($objectOrClassName)
        );
        $this->emulator->setNativeVarDump(
            function ($value) {
                // A simplified var_dump just for testing. We cannot rely on native var_dump(...)
                // because it may be overloaded by XDebug.
                print '[dump]' . var_export($value, true) . '[/dump]';
            }
        );
    }

    /**
     * @dataProvider simpleValueProvider
     */
    public function testDumpCanDumpSimpleValues(mixed $value, string $expectedOutput): void
    {
        static::assertSame($expectedOutput, $this->emulator->dump($value, 0));
    }

    public static function simpleValueProvider(): Generator
    {
        yield 'boolean true' => [true, '[dump]true[/dump]'];
        yield 'boolean false' => [false, '[dump]false[/dump]'];
        yield 'float' => [456.78, '[dump]456.78[/dump]'];
        yield 'integer' => [1234, '[dump]1234[/dump]'];
        yield 'null' => [null, '[dump]NULL[/dump]'];
        yield 'string' => ['my string', "[dump]'my string'[/dump]"];
    }

    public function testDumpCanDumpArraysContainingSimpleValuesAndNestedArrays(): void
    {
        $expectedOutput = <<<EOS
array(2) {
  ["one"]=>
  [dump]'first'[/dump]
  ["two"]=>
  array(2) {
    [0]=>
    [dump]'second'[/dump]
    [1]=>
    [dump]'third'[/dump]
  }
}
EOS;

        static::assertSame(
            $expectedOutput,
            $this->emulator->dump(
                [
                    'one' => 'first',
                    'two' => ['second', 'third'],
                ],
                0
            )
        );
    }

    public function testDumpCanDumpAnInstanceOfANonEmulatedClass(): void
    {
        $object = new stdClass;
        $object->prop1 = 21;
        $object->prop2 = 101;
        $expectedOutput = <<<EOS
[dump](object) array(
   'prop1' => 21,
   'prop2' => 101,
)[/dump]
EOS;

        static::assertSame(
            $expectedOutput,
            $this->emulator->dump($object, 0)
        );
    }

    public function testDumpCanDumpAnInstanceOfAnEmulatedClass(): void
    {
        $object = new class {
            public int $prop1 = 21;
            public string $prop2 = 'my value';
        };
        $this->emulator->registerClassEmulator(new class (get_class($object)) implements ClassEmulatorInterface {
            public function __construct(private readonly string $className)
            {
            }

            public function getClassName(): string
            {
                return $this->className;
            }

            public function getDumper(): callable
            {
                return static fn (object $object, int $depth, int $objectId) =>
                    '[object id=' . $objectId . "]\nprop1=" . $object->prop1 .
                    "\nprop2=" . $object->prop2 . "\n[/object]";
            }

            public function getMethodFetcher(): callable
            {
                return static fn () => throw new LogicException('Not implemented');
            }
        });

        $firstExpectedOutput = <<<EOS
[object id=9998]
prop1=21
prop2=my value
[/object]
EOS;
        // At depth > 0, we need to indent.
        $secondExpectedOutput = <<<EOS
[object id=9994]
  prop1=21
  prop2=my value
  [/object]
EOS;

        static::assertSame(
            $firstExpectedOutput,
            $this->emulator->dump($object, 0)
        );
        static::assertSame(
            $secondExpectedOutput,
            $this->emulator->dump($object, 1)
        );
    }

    public function testGetClassMethodsReturnsTheClassMethodsViaNativeFunctionWhenClassIsNotEmulated(): void
    {
        $className = get_class(new class {
            public function getOne(): int
            {
                return 1;
            }

            public function getTwo(): int
            {
                return 2;
            }
        });

        static::assertEquals(
            ['getOne', 'getTwo'],
            $this->emulator->getClassMethods($className)
        );
    }

    public function testGetClassMethodsReturnsTheClassMethodsViaEmulatorWhenEmulated(): void
    {
        $object = new class {
            public function getOne(): int
            {
                return 1;
            }

            public function getTwo(): int
            {
                return 2;
            }
        };
        $className = get_class($object);
        $this->emulator->registerClassEmulator(new class ($className) implements ClassEmulatorInterface {
            public function __construct(private readonly string $className)
            {
            }

            public function getClassName(): string
            {
                return $this->className;
            }

            public function getDumper(): callable
            {
                return static fn () => throw new LogicException('Not implemented');
            }

            public function getMethodFetcher(): callable
            {
                return static fn () => ['myFirstMethodFromEmulator', 'mySecondMethodFromEmulator'];
            }
        });

        static::assertEquals(
            ['myFirstMethodFromEmulator', 'mySecondMethodFromEmulator'],
            $this->emulator->getClassMethods($className)
        );
    }

    public function testGetPropertyValueFetchesThePropertyViaReflectionToIgnoreVisibility(): void
    {
        $object = new class {
            public int $myPublicProp = 21;
            private string $myPrivateProp = 'my value';
        };

        static::assertSame('my value', $this->emulator->getPropertyValue($object, 'myPrivateProp'));
    }
}
