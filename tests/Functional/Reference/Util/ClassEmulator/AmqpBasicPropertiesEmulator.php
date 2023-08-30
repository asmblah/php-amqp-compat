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

use AMQPBasicProperties;

/**
 * Class AmqpBasicPropertiesEmulator.
 *
 * Dumps instances of AMQPBasicProperties exactly as expected by the reference implementation tests,
 * and stubs the result of `get_class_methods()` with the method names in the expected order.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqpBasicPropertiesEmulator implements ClassEmulatorInterface
{
    /**
     * Dumps the given AMQPBasicProperties instance.
     */
    public function dump(
        AMQPBasicProperties $properties,
        int $depth,
        int $objectId,
        DelegatingClassEmulatorInterface $emulator
    ): string {
        return <<<OUT
object(AMQPBasicProperties)#$objectId (14) {
  ["content_type":"AMQPBasicProperties":private]=>
  {$emulator->dump($properties->getContentType(), $depth + 1)}
  ["content_encoding":"AMQPBasicProperties":private]=>
  {$emulator->dump($properties->getContentEncoding(), $depth + 1)}
  ["headers":"AMQPBasicProperties":private]=>
  {$emulator->dump($properties->getHeaders(), $depth + 1)}
  ["delivery_mode":"AMQPBasicProperties":private]=>
  {$emulator->dump($properties->getDeliveryMode(), $depth + 1)}
  ["priority":"AMQPBasicProperties":private]=>
  {$emulator->dump($properties->getPriority(), $depth + 1)}
  ["correlation_id":"AMQPBasicProperties":private]=>
  {$emulator->dump($properties->getCorrelationId(), $depth + 1)}
  ["reply_to":"AMQPBasicProperties":private]=>
  {$emulator->dump($properties->getReplyTo(), $depth + 1)}
  ["expiration":"AMQPBasicProperties":private]=>
  {$emulator->dump($properties->getExpiration(), $depth + 1)}
  ["message_id":"AMQPBasicProperties":private]=>
  {$emulator->dump($properties->getMessageId(), $depth + 1)}
  ["timestamp":"AMQPBasicProperties":private]=>
  {$emulator->dump($properties->getTimestamp(), $depth + 1)}
  ["type":"AMQPBasicProperties":private]=>
  {$emulator->dump($properties->getType(), $depth + 1)}
  ["user_id":"AMQPBasicProperties":private]=>
  {$emulator->dump($properties->getUserId(), $depth + 1)}
  ["app_id":"AMQPBasicProperties":private]=>
  {$emulator->dump($properties->getAppId(), $depth + 1)}
  ["cluster_id":"AMQPBasicProperties":private]=>
  {$emulator->dump($properties->getClusterId(), $depth + 1)}
}
OUT;

    }

    /**
     * @inheritDoc
     */
    public function getClassName(): string
    {
        return AMQPBasicProperties::class;
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
        // Methods must be defined in a specific order due to the way they are dumped
        // by `dump_methods()` in `var/ext/php-amqp/tests/_test_helpers.php.inc`.
        return fn () => [
            'getContentType',
            'getContentEncoding',
            'getHeaders',
            'getDeliveryMode',
            'getPriority',
            'getCorrelationId',
            'getReplyTo',
            'getExpiration',
            'getMessageId',
            'getTimestamp',
            'getType',
            'getUserId',
            'getAppId',
            'getClusterId',
        ];
    }
}
