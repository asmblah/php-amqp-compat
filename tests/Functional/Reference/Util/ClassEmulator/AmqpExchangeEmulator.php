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

use AMQPExchange;
use LogicException;

/**
 * Class AmqpExchangeEmulator.
 *
 * Dumps instances of AMQPExchange exactly as expected by the reference implementation tests.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqpExchangeEmulator implements ClassEmulatorInterface
{
    /**
     * Dumps the given AMQPExchange instance.
     */
    public function dump(
        AMQPExchange $exchange,
        int $depth,
        int $objectId,
        DelegatingClassEmulatorInterface $emulator
    ): string {
        return <<<OUT
object(AMQPExchange)#$objectId (9) {
  ["connection":"AMQPExchange":private]=>
  {$emulator->dump($exchange->getConnection(), $depth + 1)}
  ["channel":"AMQPExchange":private]=>
  {$emulator->dump($exchange->getChannel(), $depth + 1)}
  ["name":"AMQPExchange":private]=>
  {$emulator->dump($exchange->getName(), $depth + 1)}
  ["type":"AMQPExchange":private]=>
  {$emulator->dump($exchange->getType(), $depth + 1)}
  ["passive":"AMQPExchange":private]=>
  {$emulator->dump((bool) ($exchange->getFlags() & AMQP_PASSIVE), $depth + 1)}
  ["durable":"AMQPExchange":private]=>
  {$emulator->dump((bool) ($exchange->getFlags() & AMQP_DURABLE), $depth + 1)}
  ["auto_delete":"AMQPExchange":private]=>
  {$emulator->dump((bool) ($exchange->getFlags() & AMQP_AUTODELETE), $depth + 1)}
  ["internal":"AMQPExchange":private]=>
  {$emulator->dump((bool) ($exchange->getFlags() & AMQP_INTERNAL), $depth + 1)}
  ["arguments":"AMQPExchange":private]=>
  {$emulator->dump($exchange->getArguments(), $depth + 1)}
}
OUT;
    }

    /**
     * @inheritDoc
     */
    public function getClassName(): string
    {
        return AMQPExchange::class;
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
