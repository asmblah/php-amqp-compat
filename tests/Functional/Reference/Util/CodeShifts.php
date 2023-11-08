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

namespace Asmblah\PhpAmqpCompat\Tests\Functional\Reference\Util;

use Asmblah\PhpAmqpCompat\Tests\Functional\Reference\Util\ClassEmulator\AmqpBasicPropertiesEmulator;
use Asmblah\PhpAmqpCompat\Tests\Functional\Reference\Util\ClassEmulator\AmqpConnectionEmulator;
use Asmblah\PhpAmqpCompat\Tests\Functional\Reference\Util\ClassEmulator\AmqpEnvelopeEmulator;
use Asmblah\PhpAmqpCompat\Tests\Functional\Reference\Util\ClassEmulator\DelegatingClassEmulator;
use Asmblah\PhpAmqpCompat\Tests\Functional\Reference\Util\ClassEmulator\DelegatingClassEmulatorInterface;
use Asmblah\PhpCodeShift\CodeShift;
use Asmblah\PhpCodeShift\Shifter\Filter\FileFilter;
use Asmblah\PhpCodeShift\Shifter\Filter\MultipleFilter;
use Asmblah\PhpCodeShift\Shifter\Shift\Shift\FunctionHook\FunctionHookShiftSpec;

/**
 * Class CodeShifts.
 *
 * Applies code shifts via PHP Code Shift to allow the reference implementation php-amqp/ext-amqp's
 * own test suite to be run against this library.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class CodeShifts
{
    private static ContextResolver $contextResolver;
    private static DelegatingClassEmulatorInterface $classEmulator;

    /**
     * Fetches the ContextResolver service in use.
     */
    public static function getContextResolver(): ContextResolver
    {
        return self::$contextResolver;
    }

    /**
     * Installs all applicable code shifts for the test harness with PHP Code Shift.
     */
    public static function install(): void
    {
        self::$contextResolver = new ContextResolver();

        $codeShift = new CodeShift();

        // Pretend that php-amqp/ext-amqp, that we are emulating, is installed.
        $codeShift->shift(
            new FunctionHookShiftSpec(
                'extension_loaded',
                function ($original) {
                    return function (string $name) use ($original): bool {
                        return $name === 'amqp' || $original($name);
                    };
                }
            ),
            new MultipleFilter([
                new FileFilter('*.php'),
                new FileFilter('*.php.inc'),
            ])
        );

        $classEmulator = new DelegatingClassEmulator(self::$contextResolver);
        self::$classEmulator = $classEmulator;

        $classEmulator->registerClassEmulator(new AmqpBasicPropertiesEmulator());
        $classEmulator->registerClassEmulator(new AmqpConnectionEmulator());
        $classEmulator->registerClassEmulator(new AmqpEnvelopeEmulator());

        $codeShift->shift(
            new FunctionHookShiftSpec(
                'get_class_methods',
                function ($nativeGetClassMethods) {
                    self::$classEmulator->setNativeGetClassMethods($nativeGetClassMethods);

                    return function (object|string $objectOrClass): array {
                        $className = is_object($objectOrClass) ?
                            $objectOrClass::class :
                            $objectOrClass;

                        return self::$classEmulator->getClassMethods($className);
                    };
                }
            ),
            new MultipleFilter([
                new FileFilter('*.php'),
                new FileFilter('*.php.inc'),
            ])
        );

        $codeShift->shift(
            new FunctionHookShiftSpec(
                'var_dump',
                function ($nativeVarDump) {
                    self::$classEmulator->setNativeVarDump($nativeVarDump);

                    return function (...$vars) {
                        foreach ($vars as $var) {
                            print self::$classEmulator->dump($var, 0) . PHP_EOL;
                        }
                    };
                }
            ),
            new MultipleFilter([
                new FileFilter('*.php'),
                new FileFilter('*.php.inc'),
            ])
        );
    }
}
