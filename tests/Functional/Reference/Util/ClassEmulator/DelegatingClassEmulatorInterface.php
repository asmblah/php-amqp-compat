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

namespace Asmblah\PhpAmqpCompat\Tests\Functional\Reference\Util\ClassEmulator;

/**
 * Interface DelegatingClassEmulatorInterface.
 *
 * Slight smoke and mirrors to allow the reference implementation tests to pass
 * even though this library's classes do not match exactly (e.g. internal property names).
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface DelegatingClassEmulatorInterface
{
    /**
     * Dumps a value for `var_dump(...)`, delegating to any applicable registered class emulators.
     */
    public function dump(mixed $value, int $depth): string;

    /**
     * Fetches methods for the given class as for `get_class_methods(...)`,
     * delegating to any applicable registered class emulator.
     *
     * @return string[] Method names.
     */
    public function getClassMethods(string $className): array;

    /**
     * Fetches the value of an internal property.
     */
    public function getPropertyValue(object $instance, string $propertyName): mixed;

    /**
     * Registers a class emulator.
     */
    public function registerClassEmulator(ClassEmulatorInterface $classEmulator): void;

    /**
     * Injects the native `get_class_methods(...)` function, which must be late-bound
     * due to the load order in CodeShifts.
     */
    public function setNativeGetClassMethods(callable $nativeGetClassMethods): void;

    /**
     * Injects the native `var_dump(...)` function, which must be late-bound
     * due to the load order in CodeShifts.
     */
    public function setNativeVarDump(callable $nativeVarDump): void;
}
