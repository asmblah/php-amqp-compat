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

use AMQPConnection;
use Asmblah\PhpAmqpCompat\Bridge\AmqpBridge;
use LogicException;

/**
 * Class AmqpConnectionEmulator.
 *
 * Dumps instances of AMQPConnection exactly as expected by the reference implementation tests.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqpConnectionEmulator implements ClassEmulatorInterface
{
    /**
     * Dumps the given AMQPConnection instance.
     */
    public function dump(
        AMQPConnection $amqpConnection,
        int $depth,
        int $objectId,
        DelegatingClassEmulatorInterface $emulator
    ): string {
        $connectionConfig = AmqpBridge::getConnectionConfig($amqpConnection);

        return <<<OUT
object(AMQPConnection)#$objectId (18) {
  ["login":"AMQPConnection":private]=>
  {$emulator->dump($connectionConfig->getUser(), $depth + 1)}
  ["password":"AMQPConnection":private]=>
  {$emulator->dump($connectionConfig->getPassword(), $depth + 1)}
  ["host":"AMQPConnection":private]=>
  {$emulator->dump($connectionConfig->getHost(), $depth + 1)}
  ["vhost":"AMQPConnection":private]=>
  {$emulator->dump($connectionConfig->getVirtualHost(), $depth + 1)}
  ["port":"AMQPConnection":private]=>
  {$emulator->dump($connectionConfig->getPort(), $depth + 1)}
  ["read_timeout":"AMQPConnection":private]=>
  {$emulator->dump($connectionConfig->getReadTimeout(), $depth + 1)}
  ["write_timeout":"AMQPConnection":private]=>
  {$emulator->dump($connectionConfig->getWriteTimeout(), $depth + 1)}
  ["connect_timeout":"AMQPConnection":private]=>
  {$emulator->dump($connectionConfig->getConnectionTimeout(), $depth + 1)}
  ["rpc_timeout":"AMQPConnection":private]=>
  {$emulator->dump($connectionConfig->getRpcTimeout(), $depth + 1)}
  ["channel_max":"AMQPConnection":private]=>
  {$emulator->dump($connectionConfig->getMaxChannels(), $depth + 1)}
  ["frame_max":"AMQPConnection":private]=>
  {$emulator->dump($connectionConfig->getMaxFrameSize(), $depth + 1)}
  ["heartbeat":"AMQPConnection":private]=>
  {$emulator->dump($connectionConfig->getHeartbeatInterval(), $depth + 1)}
  ["cacert":"AMQPConnection":private]=>
  string(0) ""
  ["key":"AMQPConnection":private]=>
  string(0) ""
  ["cert":"AMQPConnection":private]=>
  string(0) ""
  ["verify":"AMQPConnection":private]=>
  bool(true)
  ["sasl_method":"AMQPConnection":private]=>
  int(0)
  ["connection_name":"AMQPConnection":private]=>
  {$emulator->dump($connectionConfig->getConnectionName(), $depth + 1)}
}
OUT;
    }

    /**
     * @inheritDoc
     */
    public function getClassName(): string
    {
        return AMQPConnection::class;
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
