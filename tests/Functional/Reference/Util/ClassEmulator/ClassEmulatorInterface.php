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
 * Interface ClassEmulatorInterface.
 *
 * Used by DelegatingClassEmulator to emulate a specific class for the reference implementation tests.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface ClassEmulatorInterface
{
    /**
     * Fetches the fully-qualified class name to emulate, e.g. AMQPBasicProperties.
     */
    public function getClassName(): string;

    /**
     * Fetches the callable to call when dumping an instance of the class being emulated.
     */
    public function getDumper(): callable;

    /**
     * Fetches the callable to call when fetching methods of the class being emulated.
     */
    public function getMethodFetcher(): callable;
}
