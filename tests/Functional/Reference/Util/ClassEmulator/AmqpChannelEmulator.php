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

use AMQPChannel;
use LogicException;

/**
 * Class AmqpChannelEmulator.
 *
 * Dumps instances of AMQPChannel exactly as expected by the reference implementation tests.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqpChannelEmulator implements ClassEmulatorInterface
{
    /**
     * Dumps the given AMQPChannel instance.
     */
    public function dump(
        AMQPChannel $amqpChannel,
        int $depth,
        int $objectId,
        DelegatingClassEmulatorInterface $emulator
    ): string {
        return <<<OUT
object(AMQPChannel)#$objectId (6) {
  ["connection":"AMQPChannel":private]=>
  {$emulator->dump($amqpChannel->getConnection(), $depth + 1)}
  ["prefetch_count":"AMQPChannel":private]=>
  int(3)
  ["prefetch_size":"AMQPChannel":private]=>
  int(0)
  ["global_prefetch_count":"AMQPChannel":private]=>
  int(0)
  ["global_prefetch_size":"AMQPChannel":private]=>
  int(0)
  ["consumers":"AMQPChannel":private]=>
  array(0) {
  }
}
OUT;
    }

    /**
     * @inheritDoc
     */
    public function getClassName(): string
    {
        return AMQPChannel::class;
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
