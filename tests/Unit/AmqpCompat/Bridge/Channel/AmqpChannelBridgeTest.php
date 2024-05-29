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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\AmqpCompat\Bridge\Channel;

use AMQPEnvelope;
use AMQPQueue;
use Asmblah\PhpAmqpCompat\Bridge\Channel\AmqpChannelBridge;
use Asmblah\PhpAmqpCompat\Bridge\Channel\ConsumerInterface;
use Asmblah\PhpAmqpCompat\Bridge\Channel\EnvelopeTransformerInterface;
use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Connection\Config\ConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transformer\MessageTransformerInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Exception\ExceptionHandlerInterface;
use Asmblah\PhpAmqpCompat\Error\ErrorReporterInterface;
use Asmblah\PhpAmqpCompat\Logger\LoggerInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use LogicException;
use Mockery\MockInterface;
use PhpAmqpLib\Channel\AMQPChannel as AmqplibChannel;

/**
 * Class AmqpChannelBridgeTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqpChannelBridgeTest extends AbstractTestCase
{
    private MockInterface&AmqplibChannel $amqplibChannel;
    private AmqpChannelBridge $channelBridge;
    private MockInterface&AmqpConnectionBridgeInterface $connectionBridge;
    private MockInterface&ConsumerInterface $consumer;
    private MockInterface&EnvelopeTransformerInterface $envelopeTransformer;
    private MockInterface&ErrorReporterInterface $errorReporter;
    private MockInterface&ExceptionHandlerInterface $exceptionHandler;
    private MockInterface&LoggerInterface $logger;
    private MockInterface&MessageTransformerInterface $messageTransformer;

    public function setUp(): void
    {
        $this->amqplibChannel = mock(AmqplibChannel::class);
        $this->envelopeTransformer = mock(EnvelopeTransformerInterface::class);
        $this->errorReporter = mock(ErrorReporterInterface::class);
        $this->exceptionHandler = mock(ExceptionHandlerInterface::class);
        $this->logger = mock(LoggerInterface::class);
        $this->messageTransformer = mock(MessageTransformerInterface::class);
        $this->connectionBridge = mock(AmqpConnectionBridgeInterface::class, [
            'getConnectionConfig' => mock(ConnectionConfigInterface::class, [
                'getReadTimeout' => 123.45,
            ]),
            'getEnvelopeTransformer' => $this->envelopeTransformer,
            'getErrorReporter' => $this->errorReporter,
            'getExceptionHandler' => $this->exceptionHandler,
            'getLogger' => $this->logger,
            'getMessageTransformer' => $this->messageTransformer,
        ]);
        $this->consumer = mock(ConsumerInterface::class);

        $this->channelBridge = new AmqpChannelBridge(
            $this->connectionBridge,
            $this->amqplibChannel,
            $this->consumer
        );
    }

    public function testConsumeEnvelopeDelegatesToTheConsumer(): void
    {
        $amqpEnvelope = mock(AMQPEnvelope::class, [
            'getConsumerTag' => 'my_consumer_tag',
        ]);
        $amqpQueue = mock(AMQPQueue::class);
        $this->channelBridge->subscribeConsumer('my_consumer_tag', $amqpQueue);

        $this->consumer->expects()
            ->consumeEnvelope($amqpEnvelope, $amqpQueue)
            ->once();

        $this->channelBridge->consumeEnvelope($amqpEnvelope);
    }

    public function testConsumeEnvelopeThrowsWhenNoConsumerIsRegisteredForTag(): void
    {
        $amqpEnvelope = mock(AMQPEnvelope::class, [
            'getConsumerTag' => 'my_consumer_tag',
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            AmqpChannelBridge::class .
            '::consumeEnvelope(): No consumer registered for consumer tag "my_consumer_tag"'
        );

        $this->channelBridge->consumeEnvelope($amqpEnvelope);
    }

    public function testGetAmqplibChannelReturnsTheChannel(): void
    {
        static::assertSame($this->amqplibChannel, $this->channelBridge->getAmqplibChannel());
    }

    public function testGetConnectionBridgeReturnsTheBridge(): void
    {
        static::assertSame($this->connectionBridge, $this->channelBridge->getConnectionBridge());
    }

    public function testGetEnvelopeTransformerReturnsTheTransformer(): void
    {
        static::assertSame($this->envelopeTransformer, $this->channelBridge->getEnvelopeTransformer());
    }

    public function testGetErrorReporterReturnsTheReporter(): void
    {
        static::assertSame($this->errorReporter, $this->channelBridge->getErrorReporter());
    }

    public function testGetExceptionHandlerReturnsTheHandler(): void
    {
        static::assertSame($this->exceptionHandler, $this->channelBridge->getExceptionHandler());
    }

    public function testGetLoggerReturnsTheLogger(): void
    {
        static::assertSame($this->logger, $this->channelBridge->getLogger());
    }

    public function testGetMessageTransformerReturnsTheTransformer(): void
    {
        static::assertSame($this->messageTransformer, $this->channelBridge->getMessageTransformer());
    }

    public function testGetReadTimeoutReturnsTheTimeout(): void
    {
        static::assertSame(123.45, $this->channelBridge->getReadTimeout());
    }

    public function testGetSubscribedConsumersFetchesMapFromConsumerTagToQueue(): void
    {
        $amqpQueue1 = mock(AMQPQueue::class);
        $this->channelBridge->subscribeConsumer('my-first-consumer', $amqpQueue1);
        $amqpQueue2 = mock(AMQPQueue::class);
        $this->channelBridge->subscribeConsumer('my-second-consumer', $amqpQueue2);

        $consumers = $this->channelBridge->getSubscribedConsumers();

        static::assertCount(2, $consumers);
        static::assertSame($amqpQueue1, $consumers['my-first-consumer']);
        static::assertSame($amqpQueue2, $consumers['my-second-consumer']);
    }

    public function testIsConsumerSubscribedReturnsTrueWhenSubscribed(): void
    {
        $amqpQueue = mock(AMQPQueue::class);
        $this->channelBridge->subscribeConsumer('my_consumer_tag', $amqpQueue);

        static::assertTrue($this->channelBridge->isConsumerSubscribed('my_consumer_tag'));
    }

    public function testIsConsumerSubscribedReturnsFalseWhenNotSubscribed(): void
    {
        static::assertFalse($this->channelBridge->isConsumerSubscribed('invalid_consumer_tag'));
    }

    public function testUnregisterChannelUnregistersChannelBridgeViaConnectionBridge(): void
    {
        $this->connectionBridge->expects()
            ->unregisterChannelBridge($this->channelBridge)
            ->once();

        $this->channelBridge->unregisterChannel();
    }

    public function testUnsubscribeConsumerUnsubscribesTheConsumer(): void
    {
        $amqpQueue1 = mock(AMQPQueue::class);
        $this->channelBridge->subscribeConsumer('my_first_consumer_tag', $amqpQueue1);
        $amqpQueue2 = mock(AMQPQueue::class);
        $this->channelBridge->subscribeConsumer('my_second_consumer_tag', $amqpQueue2);

        $this->channelBridge->unsubscribeConsumer('my_first_consumer_tag');

        static::assertFalse($this->channelBridge->isConsumerSubscribed('my_first_consumer_tag'));
        static::assertTrue($this->channelBridge->isConsumerSubscribed('my_second_consumer_tag'));
    }
}
