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

use Asmblah\PhpAmqpCompat\Tests\Functional\Reference\ReferenceImplementationTest;
use Asmblah\PhpAmqpCompat\Tests\Functional\Reference\Util\ContextResolver;
use LogicException;
use ReflectionProperty;

/**
 * Class DelegatingClassEmulator.
 *
 * Slight smoke and mirrors to allow the reference implementation tests to pass
 * even though this library's classes do not match exactly (e.g. internal property names).
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class DelegatingClassEmulator implements DelegatingClassEmulatorInterface
{
    /**
     * @var array<string, callable>
     */
    private array $classNameToInstanceDumper = [];
    /**
     * @var array<string, callable>
     */
    private array $classNameToMethodFetcher = [];
    /**
     * @var callable
     */
    private mixed $nativeGetClassMethods;
    /**
     * @var callable
     */
    private mixed $nativeVarDump;
    /**
     * @var array<string, int>
     */
    private array $objectIndexesByFile = [];

    public function __construct(
        private readonly ContextResolver $contextResolver
    ) {
    }

    /**
     * @inheritDoc
     */
    public function dump(mixed $value, int $depth): string
    {
        if (is_object($value) && array_key_exists($value::class, $this->classNameToInstanceDumper)) {
            // Class is being emulated for the purposes of the test harness.
            $objectId = $this->getStubObjectId();

            $result = $this->classNameToInstanceDumper[$value::class](
                $value,
                $depth,
                $objectId,
                $this
            );
        } else {
            // Class is not being emulated: just defer to the native `var_dump(...)` handling.
            $result = $this->nativeDump($value);
        }

        $padding = $depth > 0 ? '  ' : '';

        return str_replace(PHP_EOL, PHP_EOL . $padding, $result);
    }

    /**
     * @inheritDoc
     */
    public function getClassMethods(string $className): array
    {
        return array_key_exists($className, $this->classNameToMethodFetcher) ?
            $this->classNameToMethodFetcher[$className]() :
            ($this->nativeGetClassMethods)($className);
    }

    /**
     * @inheritDoc
     */
    public function getPropertyValue(object $instance, string $propertyName): mixed
    {
        $reflectionProperty = new ReflectionProperty($instance, $propertyName);

        return $reflectionProperty->getValue($instance);
    }

    /**
     * Fetches the next predefined stub object ID for the current reference implementation test.
     */
    private function getStubObjectId(): int
    {
        $file = basename($this->contextResolver->getContext()['file']);

        if (array_key_exists($file, $this->objectIndexesByFile)) {
            $this->objectIndexesByFile[$file]++;
        } else {
            $this->objectIndexesByFile[$file] = 0;
        }

        $ids = ReferenceImplementationTest::VAR_DUMP_OBJECT_IDS[$file] ?? [];

        if ($this->objectIndexesByFile[$file] > count($ids) - 1) {
            throw new LogicException(__METHOD__ . '(): Ran out of object IDs to dump');
        }

        return $ids[$this->objectIndexesByFile[$file]];
    }

    /**
     * Captures output from the overridden native var_dump(...) as a string,
     * so that it may be indented etc. where used.
     */
    private function nativeDump(mixed $value): string
    {
        ob_start();
        ($this->nativeVarDump)($value);

        return rtrim(ob_get_clean(), PHP_EOL);
    }

    /**
     * @inheritDoc
     */
    public function registerClassEmulator(ClassEmulatorInterface $classEmulator): void
    {
        $className = $classEmulator->getClassName();

        $this->classNameToInstanceDumper[$className] = $classEmulator->getDumper();
        $this->classNameToMethodFetcher[$className] = $classEmulator->getMethodFetcher();
    }

    /**
     * @inheritDoc
     */
    public function setNativeGetClassMethods(callable $nativeGetClassMethods): void
    {
        $this->nativeGetClassMethods = $nativeGetClassMethods;
    }

    /**
     * @inheritDoc
     */
    public function setNativeVarDump(callable $nativeVarDump): void
    {
        $this->nativeVarDump = $nativeVarDump;
    }
}
