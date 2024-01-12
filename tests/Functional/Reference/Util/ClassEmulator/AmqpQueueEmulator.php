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

use AMQPQueue;
use LogicException;

/**
 * Class AmqpQueueEmulator.
 *
 * Dumps instances of AMQPQueue exactly as expected by the reference implementation tests.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqpQueueEmulator implements ClassEmulatorInterface
{
    /**
     * Dumps the given AMQPQueue instance.
     */
    public function dump(
        AMQPQueue $queue,
        int $depth,
        int $objectId,
        DelegatingClassEmulatorInterface $emulator
    ): string {
        return <<<OUT
object(AMQPQueue)#$objectId (9) {
  ["connection":"AMQPQueue":private]=>
  {$emulator->dump($queue->getConnection(), $depth + 1)}
  ["channel":"AMQPQueue":private]=>
  {$emulator->dump($queue->getChannel(), $depth + 1)}
  ["name":"AMQPQueue":private]=>
  {$emulator->dump($queue->getName(), $depth + 1)}
  ["consumer_tag":"AMQPQueue":private]=>
  {$emulator->dump($queue->getConsumerTag(), $depth + 1)}
  ["passive":"AMQPQueue":private]=>
  {$emulator->dump((bool) ($queue->getFlags() & AMQP_PASSIVE), $depth + 1)}
  ["durable":"AMQPQueue":private]=>
  {$emulator->dump((bool) ($queue->getFlags() & AMQP_DURABLE), $depth + 1)}
  ["exclusive":"AMQPQueue":private]=>
  {$emulator->dump((bool) ($queue->getFlags() & AMQP_EXCLUSIVE), $depth + 1)}
  ["auto_delete":"AMQPQueue":private]=>
  {$emulator->dump((bool) ($queue->getFlags() & AMQP_AUTODELETE), $depth + 1)}
  ["arguments":"AMQPQueue":private]=>
  {$emulator->dump($queue->getArguments(), $depth + 1)}
}
OUT;
    }

    /**
     * @inheritDoc
     */
    public function getClassName(): string
    {
        return AMQPQueue::class;
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
