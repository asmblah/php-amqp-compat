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

use AMQPEnvelope;
use LogicException;

/**
 * Class AmqpEnvelopeEmulator.
 *
 * Dumps instances of AMQPEnvelope exactly as expected by the reference implementation tests.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqpEnvelopeEmulator implements ClassEmulatorInterface
{
    /**
     * Dumps the given AMQPEnvelope instance.
     */
    public function dump(
        AMQPEnvelope $envelope,
        int $depth,
        int $objectId,
        DelegatingClassEmulatorInterface $emulator
    ): string {
        $body = $emulator->getPropertyValue($envelope, 'body');

        return <<<OUT
object(AMQPEnvelope)#$objectId (20) {
  ["content_type":"AMQPBasicProperties":private]=>
  {$emulator->dump($envelope->getContentType(), $depth + 1)}
  ["content_encoding":"AMQPBasicProperties":private]=>
  {$emulator->dump($envelope->getContentEncoding(), $depth + 1)}
  ["headers":"AMQPBasicProperties":private]=>
  {$emulator->dump($envelope->getHeaders(), $depth + 1)}
  ["delivery_mode":"AMQPBasicProperties":private]=>
  {$emulator->dump($envelope->getDeliveryMode(), $depth + 1)}
  ["priority":"AMQPBasicProperties":private]=>
  {$emulator->dump($envelope->getPriority(), $depth + 1)}
  ["correlation_id":"AMQPBasicProperties":private]=>
  {$emulator->dump($envelope->getCorrelationId(), $depth + 1)}
  ["reply_to":"AMQPBasicProperties":private]=>
  {$emulator->dump($envelope->getReplyTo(), $depth + 1)}
  ["expiration":"AMQPBasicProperties":private]=>
  {$emulator->dump($envelope->getExpiration(), $depth + 1)}
  ["message_id":"AMQPBasicProperties":private]=>
  {$emulator->dump($envelope->getMessageId(), $depth + 1)}
  ["timestamp":"AMQPBasicProperties":private]=>
  {$emulator->dump($envelope->getTimestamp(), $depth + 1)}
  ["type":"AMQPBasicProperties":private]=>
  {$emulator->dump($envelope->getType(), $depth + 1)}
  ["user_id":"AMQPBasicProperties":private]=>
  {$emulator->dump($envelope->getUserId(), $depth + 1)}
  ["app_id":"AMQPBasicProperties":private]=>
  {$emulator->dump($envelope->getAppId(), $depth + 1)}
  ["cluster_id":"AMQPBasicProperties":private]=>
  {$emulator->dump($envelope->getClusterId(), $depth + 1)}
  ["body":"AMQPEnvelope":private]=>
  {$emulator->dump($body, $depth + 1)}
  ["consumer_tag":"AMQPEnvelope":private]=>
  {$emulator->dump($envelope->getConsumerTag(), $depth + 1)}
  ["delivery_tag":"AMQPEnvelope":private]=>
  {$emulator->dump($envelope->getDeliveryTag(), $depth + 1)}
  ["is_redelivery":"AMQPEnvelope":private]=>
  {$emulator->dump($envelope->isRedelivery(), $depth + 1)}
  ["exchange_name":"AMQPEnvelope":private]=>
  {$emulator->dump($envelope->getExchangeName(), $depth + 1)}
  ["routing_key":"AMQPEnvelope":private]=>
  {$emulator->dump($envelope->getRoutingKey(), $depth + 1)}
}
OUT;
    }

    /**
     * @inheritDoc
     */
    public function getClassName(): string
    {
        return AMQPEnvelope::class;
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
