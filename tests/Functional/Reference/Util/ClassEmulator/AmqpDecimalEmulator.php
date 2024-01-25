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

use AMQPDecimal;
use LogicException;

/**
 * Class AmqpDecimalEmulator.
 *
 * Dumps instances of AMQPDecimal exactly as expected by the reference implementation tests.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqpDecimalEmulator implements ClassEmulatorInterface
{
    /**
     * Dumps the given AMQPDecimal instance.
     */
    public function dump(
        AMQPDecimal $decimal,
        int $depth,
        int $objectId,
        DelegatingClassEmulatorInterface $emulator
    ): string {
        return <<<OUT
object(AMQPDecimal)#$objectId (2) {
  ["exponent":"AMQPDecimal":private]=>
  {$emulator->dump($decimal->getExponent(), $depth + 1)}
  ["significand":"AMQPDecimal":private]=>
  {$emulator->dump($decimal->getSignificand(), $depth + 1)}
}
OUT;
    }

    /**
     * @inheritDoc
     */
    public function getClassName(): string
    {
        return AMQPDecimal::class;
    }

    /**
     * @inheritDoc
     */
    public function getDumper(): callable
    {
        return $this->dump(...);
    }

    /**
     * @inheritDoc
     */
    public function getMethodFetcher(): callable
    {
        return fn () => throw new LogicException(__METHOD__ . '(): Not implemented');
    }
}
