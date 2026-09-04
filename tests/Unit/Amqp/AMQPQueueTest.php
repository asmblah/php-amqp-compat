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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\Amqp;

use AMQPChannel;
use AMQPChannelException;
use AMQPEnvelope;
use AMQPEnvelopeException;
use AMQPQueue;
use AMQPQueueException;
use Asmblah\PhpAmqpCompat\Bridge\AmqpBridge;
use Asmblah\PhpAmqpCompat\Bridge\Channel\AmqpChannelBridgeInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Channel\ChannelInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Logger\LoggerInterface;
use Asmblah\PhpAmqpCompat\Exception\StopConsumptionException;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Closure;
use Mockery;
use Mockery\MockInterface;
use stdClass;

/**
 * Class AMQPQueueTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AMQPQueueTest extends AbstractTestCase
{
    private MockInterface&AMQPChannel $amqpChannel;
    private AMQPQueue $amqpQueue;
    private MockInterface&ChannelInterface $channel;
    private MockInterface&AmqpChannelBridgeInterface $channelBridge;
    private MockInterface&LoggerInterface $logger;

    public function setUp(): void
    {
        $this->amqpChannel = mock(AMQPChannel::class);
        $this->channel = mock(ChannelInterface::class, [
            'basicAck' => null,
            'basicConsume' => 'my_consumer_tag',
            'basicNack' => null,
        ]);
        $this->logger = mock(LoggerInterface::class, [
            'debug' => null,
        ]);
        $this->channelBridge = mock(AmqpChannelBridgeInterface::class, [
            'acquireChannel' => $this->channel,
            'getLogger' => $this->logger,
            'getReadTimeout' => 12,
            'getSubscribedConsumers' => [
                'consumer-tag-1' => mock(AMQPQueue::class, [
                    'getName' => 'my_queue_1',
                ]),
                'consumer-tag-2' => mock(AMQPQueue::class, [
                    'getName' => 'my_queue_2',
                ]),
            ],
            'isConsumerSubscribed' => true,
            'setConsumptionCallback' => null,
            'subscribeConsumer' => null,
        ]);
        AmqpBridge::bridgeChannel($this->amqpChannel, $this->channelBridge);

        $this->amqpQueue = new AMQPQueue($this->amqpChannel);
    }

    public function testConstructorNotBeingCalledIsHandledCorrectly(): void
    {
        $extendedAmqpQueue = new class extends AMQPQueue {
            public function __construct()
            {
                // Deliberately omit the call to the super constructor.
            }
        };

        $this->expectException(AMQPChannelException::class);
        $this->expectExceptionMessage('Could not declare queue. Stale reference to the channel object.');

        $extendedAmqpQueue->declareQueue();
    }

    public function testAckLogsAttemptAsDebug(): void
    {
        $this->amqpQueue->setName('my_queue');

        $this->logger->expects()
            ->debug('AMQPQueue::ack(): Acknowledgement attempt', [
                'delivery_tag' => 123,
                'flags' => AMQP_NOPARAM,
                'queue' => 'my_queue',
            ])
            ->once();

        $this->amqpQueue->ack(123);
    }

    public function testAckAcknowledgesViaChannelWithDefaultFlags(): void
    {
        $this->channel->expects()
            ->basicAck(321, false, AMQPQueueException::class, 'AMQPQueue::ack')
            ->once();

        $this->amqpQueue->ack(321);
    }

    public function testAckAcknowledgesViaChannelWithMultipleFlag(): void
    {
        $this->channel->expects()
            ->basicAck(321, true, AMQPQueueException::class, 'AMQPQueue::ack')
            ->once();

        $this->amqpQueue->ack(321, AMQP_MULTIPLE);
    }

    public function testAckHandlesExceptionCorrectly(): void
    {
        $this->channel->allows()
            ->basicAck(123, false, AMQPQueueException::class, 'AMQPQueue::ack')
            ->andThrow(new AMQPQueueException('Bang!'));

        $this->expectException(AMQPQueueException::class);
        $this->expectExceptionMessage('Bang!');

        $this->amqpQueue->ack(123);
    }

    public function testAckReturnsTrue(): void
    {
        static::assertTrue($this->amqpQueue->ack(321));
    }

    public function testAckLogsSuccessAsDebug(): void
    {
        $this->amqpQueue->setName('my_queue');

        $this->logger->expects()
            ->debug('AMQPQueue::ack(): Message acknowledged')
            ->once();

        $this->amqpQueue->ack(123);
    }

    public function testConsumeLogsStartAttemptAsDebugWhenNoFlagsNorCallbackGiven(): void
    {
        $this->amqpQueue->setName('my_queue');

        $this->logger->expects()
            ->debug('AMQPQueue::consume(): Consumer start attempt', [
                'consumer_tag' => 'my_input_consumer_tag',
                'flags' => AMQP_NOPARAM,
                'queue' => 'my_queue',
                'subscribed_consumers' => [
                    'consumer-tag-1' => 'my_queue_1',
                    'consumer-tag-2' => 'my_queue_2',
                ],
            ])
            ->once();

        $this->amqpQueue->consume(null, AMQP_NOPARAM, 'my_input_consumer_tag');
    }

    public function testConsumeLogsStartAttemptAsDebugWhenJustConsumeFlagAndCallbackGiven(): void
    {
        $consumerCallback = function () {};
        $this->amqpQueue->setName('my_queue');
        $this->channel->allows('wait')
            ->andThrow(new StopConsumptionException());

        $this->logger->expects()
            ->debug('AMQPQueue::consume(): Consumer start attempt', [
                'consumer_tag' => null,
                'flags' => AMQP_JUST_CONSUME,
                'queue' => 'my_queue',
                'subscribed_consumers' => [
                    'consumer-tag-1' => 'my_queue_1',
                    'consumer-tag-2' => 'my_queue_2',
                ],
            ])
            ->once();

        $this->amqpQueue->consume($consumerCallback, AMQP_JUST_CONSUME);
    }

    public function testConsumeLogsSubscriptionAttemptAsDebugWhenCallbackGivenButNoFlags(): void
    {
        $consumerCallback = function () {};
        $this->amqpQueue->setName('my_queue');
        $this->channel->allows('wait')
            ->andThrow(new StopConsumptionException());

        $this->logger->expects()
            ->debug('AMQPQueue::consume(): Consumer subscription attempt', [
                'consumer_tag' => 'my_input_consumer_tag',
                'flags' => AMQP_NOPARAM,
                'queue' => 'my_queue',
                'subscribed_consumers' => [
                    'consumer-tag-1' => 'my_queue_1',
                    'consumer-tag-2' => 'my_queue_2',
                ],
            ])
            ->once();

        $this->amqpQueue->consume($consumerCallback, AMQP_NOPARAM, 'my_input_consumer_tag');
    }

    public function testConsumeWithNoFlagsSubscribesConsumerWhenNoCallbackGiven(): void
    {
        $this->amqpQueue->setName('my_queue');
        $this->channel->allows()
            ->basicConsume(
                'my_queue',
                'my_input_consumer_tag',
                false,
                false,
                false,
                Mockery::type(Closure::class),
                AMQPQueueException::class,
                'AMQPQueue::consume'
            )
            ->andReturn('my_output_consumer_tag');

        $this->channelBridge->expects()
            ->subscribeConsumer('my_output_consumer_tag', $this->amqpQueue)
            ->once();
        $this->logger->expects()
            ->debug('AMQPQueue::consume(): Consumer subscribed')
            ->once();
        $this->logger->expects()
            ->debug('AMQPQueue::consume(): Just consuming - not subscribing')
            ->never();
        $this->logger->expects()
            ->debug('AMQPQueue::consume(): Consumer not yet starting')
            ->once();
        $this->logger->expects()
            ->debug('AMQPQueue::consume(): Consumer stopped')
            ->never();

        $this->amqpQueue->consume(null, AMQP_NOPARAM, 'my_input_consumer_tag');
    }

    public function testConsumeWithNoFlagsSubscribesConsumerWhenCallbackGiven(): void
    {
        $consumerCallback = function () {};
        $this->amqpQueue->setName('my_queue');
        $this->channel->allows('wait')
            ->andThrow(new StopConsumptionException());

        $this->channelBridge->expects()
            ->subscribeConsumer('my_consumer_tag', $this->amqpQueue)
            ->once();
        $this->logger->expects()
            ->debug('AMQPQueue::consume(): Consumer subscribed')
            ->once();
        $this->logger->expects()
            ->debug('AMQPQueue::consume(): Just consuming - not subscribing')
            ->never();
        $this->logger->expects()
            ->debug('AMQPQueue::consume(): Consumer not yet starting')
            ->never();
        $this->logger->expects()
            ->debug('AMQPQueue::consume(): Consumer stopped')
            ->once();

        $this->amqpQueue->consume($consumerCallback);
    }

    // This scenario is possible, but pointless as nothing will happen.
    public function testConsumeWithJustConsumeFlagDoesNotSubscribeConsumerWhenNoCallbackGiven(): void
    {
        $consumerCallback = null;
        $this->amqpQueue->setName('my_queue');

        $this->channelBridge->expects('subscribeConsumer')
            ->never();
        $this->logger->expects()
            ->debug('AMQPQueue::consume(): Consumer subscribed')
            ->never();
        $this->logger->expects()
            ->debug('AMQPQueue::consume(): Just consuming - not subscribing')
            ->once();
        $this->logger->expects()
            ->debug('AMQPQueue::consume(): Consumer not yet starting')
            ->once();
        $this->logger->expects()
            ->debug('AMQPQueue::consume(): Consumer stopped')
            ->never();

        $this->amqpQueue->consume($consumerCallback, AMQP_JUST_CONSUME);
    }

    public function testConsumeWithNoFlagsProvidesCallbackThatConsumesViaChannelBridge(): void
    {
        $this->amqpQueue->setName('my_queue');
        $internalCallback = null;
        $this->channel->allows()
            ->basicConsume(
                'my_queue',
                'my_input_consumer_tag',
                false,
                false,
                false,
                Mockery::type(Closure::class),
                AMQPQueueException::class,
                'AMQPQueue::consume'
            )
            ->andReturnUsing(function (
                string $queueName,
                string $consumerTag,
                bool $noLocal,
                bool $autoAck,
                bool $exclusive,
                callable $callback
            ) use (&$internalCallback) {
                $internalCallback = $callback;

                return 'my_consumer_tag';
            });
        $amqpEnvelope = mock(AMQPEnvelope::class, [
            'getConsumerTag' => 'my_consumer_tag',
        ]);

        $this->channelBridge->expects()
            ->consumeEnvelope($amqpEnvelope)
            ->once();

        $this->amqpQueue->consume(null, AMQP_NOPARAM, 'my_input_consumer_tag');
        $internalCallback($amqpEnvelope);
    }

    public function testConsumeWithJustConsumeFlagProvidesCallbackToChannelBridge(): void
    {
        $consumerCallback = function () {};
        $this->amqpQueue->setName('my_queue');
        $this->channel->allows('wait')
            ->andThrow(new StopConsumptionException());

        $this->channelBridge->expects()
            ->setConsumptionCallback($consumerCallback)
            ->once();

        $this->amqpQueue->consume($consumerCallback, AMQP_JUST_CONSUME);
    }

    public function testConsumeWithJustConsumeFlagDoesNotSubscribeConsumer(): void
    {
        $consumerCallback = function () {};
        $this->amqpQueue->setName('my_queue');
        $this->channel->allows('wait')
            ->andThrow(new StopConsumptionException());

        $this->channel->expects('basicConsume')
            ->never();
        $this->logger->expects()
            ->debug('AMQPQueue::consume(): Consumer subscribed')
            ->never();
        $this->logger->expects()
            ->debug('AMQPQueue::consume(): Just consuming - not subscribing')
            ->once();
        $this->logger->expects()
            ->debug('AMQPQueue::consume(): Consumer not yet starting')
            ->never();
        $this->logger->expects()
            ->debug('AMQPQueue::consume(): Consumer stopped')
            ->once();

        $this->amqpQueue->consume($consumerCallback, AMQP_JUST_CONSUME);
    }

    public function testConsumeProvidesCallbackThatRaisesAmqpEnvelopeExceptionIfConsumerTagIsUnknown(): void
    {
        $this->amqpQueue->setName('my_queue');
        $internalCallback = null;
        $this->channel->allows()
            ->basicConsume(
                'my_queue',
                'my_input_consumer_tag',
                false,
                false,
                false,
                Mockery::type(Closure::class),
                AMQPQueueException::class,
                'AMQPQueue::consume'
            )
            ->andReturnUsing(function (
                string $queueName,
                string $consumerTag,
                bool $noLocal,
                bool $autoAck,
                bool $exclusive,
                callable $callback
            ) use (&$internalCallback) {
                $internalCallback = $callback;

                return 'my_consumer_tag';
            });
        $amqpEnvelope = mock(AMQPEnvelope::class, [
            'getConsumerTag' => 'my_unknown_consumer_tag',
        ]);
        $this->channelBridge->expects()
            ->isConsumerSubscribed('my_unknown_consumer_tag')
            ->andReturnFalse();

        $this->expectException(AMQPEnvelopeException::class);
        $this->expectExceptionMessage('Orphaned envelope');
        $this->amqpQueue->consume(null, AMQP_NOPARAM, 'my_input_consumer_tag');
        try {
            $internalCallback($amqpEnvelope);
        } catch (AMQPEnvelopeException $exception) {
            static::assertSame($amqpEnvelope, $exception->envelope);
            throw $exception;
        }
    }

    public function testConsumeHandlesExceptionFromBasicConsumeCorrectly(): void
    {
        $this->amqpQueue->setName('my_queue');
        $consumerCallback = function () {};
        $this->channel->allows('basicConsume')
            ->andThrow(new AMQPQueueException('Bang!'));

        $this->expectException(AMQPQueueException::class);
        $this->expectExceptionMessage('Bang!');

        $this->amqpQueue->consume($consumerCallback, AMQP_NOPARAM, 'my_input_consumer_tag');
    }

    public function testConsumeWaitsUpToConfiguredReadTimeout(): void
    {
        $consumerCallback = function () {};
        $this->amqpQueue->setName('my_queue');

        $this->channel->expects()
            ->wait(12, AMQPQueueException::class, 'AMQPQueue::consume')
            ->once()
            ->andThrow(new StopConsumptionException());

        $this->amqpQueue->consume($consumerCallback);
    }

    public function testConsumeHandlesExceptionsDuringWait(): void
    {
        $consumerCallback = function () {};
        $this->amqpQueue->setName('my_queue');
        $this->channel->allows('wait')
            ->andThrow(new AMQPQueueException('Bang!'));

        $this->expectException(AMQPQueueException::class);
        $this->expectExceptionMessage('Bang!');

        $this->amqpQueue->consume($consumerCallback);
    }

    public function testDeclareQueueDeclaresViaChannel(): void
    {
        $this->amqpQueue->setName('my_queue');
        $this->amqpQueue->setFlags(AMQP_PASSIVE | AMQP_EXCLUSIVE | AMQP_AUTODELETE);
        $this->amqpQueue->setArguments(['x-dead-letter-exchange' => 'my_retry_exchange']);

        $this->channel->expects()
            ->declareQueue(
                'my_queue',
                true,
                false,
                true,
                true,
                false,
                ['x-dead-letter-exchange' => 'my_retry_exchange'],
                AMQPQueueException::class,
                'AMQPQueue::declareQueue'
            )
            ->once()
            ->andReturn(['name' => 'my_queue', 'count' => 21]);

        static::assertSame(21, $this->amqpQueue->declareQueue(), 'Message count should be returned');
    }

    public function testDeclareQueueStoresReturnedQueueName(): void
    {
        $this->amqpQueue->setFlags(AMQP_PASSIVE | AMQP_EXCLUSIVE | AMQP_AUTODELETE);
        $this->amqpQueue->setArguments(['x-dead-letter-exchange' => 'my_retry_exchange']);
        $this->channel->allows()
            ->declareQueue(
                '',
                true,
                false,
                true,
                true,
                false,
                ['x-dead-letter-exchange' => 'my_retry_exchange'],
                AMQPQueueException::class,
                'AMQPQueue::declareQueue'
            )
            ->andReturn(['name' => 'my_generated_queue', 'count' => 21]);

        $this->amqpQueue->declareQueue();

        static::assertSame(
            'my_generated_queue',
            $this->amqpQueue->getName(),
            'Returned queue name should be used'
        );
    }

    public function testDeclareQueueHandlesExceptionCorrectly(): void
    {
        $this->amqpQueue->setName('my_queue');
        $this->channel->allows('declareQueue')
            ->andThrow(new AMQPQueueException('my text'));

        $this->expectException(AMQPQueueException::class);
        $this->expectExceptionMessage('my text');

        $this->amqpQueue->declareQueue();
    }

    public function testDeleteLogsAttemptAsDebug(): void
    {
        $this->amqpQueue->setName('my_queue');
        $this->channel->allows()
            ->deleteQueue('my_queue', false, false, false, AMQPQueueException::class, 'AMQPQueue::delete')
            ->andReturn(0);

        $this->logger->expects()
            ->debug('AMQPQueue::delete(): Queue deletion attempt', [
                'flags' => AMQP_NOPARAM,
                'queue' => 'my_queue',
            ])
            ->once();

        $this->amqpQueue->delete();
    }

    public function testDeleteDeletesQueueViaChannelWithDefaultFlags(): void
    {
        $this->amqpQueue->setName('my_queue');

        $this->channel->expects()
            ->deleteQueue('my_queue', false, false, false, AMQPQueueException::class, 'AMQPQueue::delete')
            ->once();

        $this->amqpQueue->delete();
    }

    public function testDeleteDeletesQueueViaChannelWithIfUnusedFlag(): void
    {
        $this->amqpQueue->setName('my_queue');

        $this->channel->expects()
            ->deleteQueue('my_queue', true, false, false, AMQPQueueException::class, 'AMQPQueue::delete')
            ->once();

        $this->amqpQueue->delete(AMQP_IFUNUSED);
    }

    public function testDeleteDeletesQueueViaChannelWithIfEmptyFlag(): void
    {
        $this->amqpQueue->setName('my_queue');

        $this->channel->expects()
            ->deleteQueue('my_queue', false, true, false, AMQPQueueException::class, 'AMQPQueue::delete')
            ->once();

        $this->amqpQueue->delete(AMQP_IFEMPTY);
    }

    public function testDeleteDeletesQueueViaChannelWithNoWaitFlagSetOnQueue(): void
    {
        $this->amqpQueue->setName('my_queue');
        $this->amqpQueue->setFlags(AMQP_NOWAIT);

        $this->channel->expects()
            ->deleteQueue('my_queue', false, false, true, AMQPQueueException::class, 'AMQPQueue::delete')
            ->once();

        $this->amqpQueue->delete();
    }

    public function testDeleteHandlesExceptionCorrectly(): void
    {
        $this->amqpQueue->setName('my_queue');
        $this->channel->allows()
            ->deleteQueue('my_queue', false, false, false, AMQPQueueException::class, 'AMQPQueue::delete')
            ->andThrow(new AMQPQueueException('Bang!'));

        $this->expectException(AMQPQueueException::class);
        $this->expectExceptionMessage('Bang!');

        $this->amqpQueue->delete();
    }

    public function testDeleteReturnsTheNumberOfMessagesThatWereInTheDeletedQueue(): void
    {
        $this->amqpQueue->setName('my_queue');
        $this->channel->allows()
            ->deleteQueue('my_queue', false, false, false, AMQPQueueException::class, 'AMQPQueue::delete')
            ->andReturn(21);

        static::assertSame(21, $this->amqpQueue->delete());
    }

    public function testDeleteLogsSuccessAsDebug(): void
    {
        $this->amqpQueue->setName('my_queue');
        $this->channel->allows()
            ->deleteQueue('my_queue', false, false, false, AMQPQueueException::class, 'AMQPQueue::delete');

        $this->logger->expects()
            ->debug('AMQPQueue::delete(): Queue deleted')
            ->once();

        $this->amqpQueue->delete();
    }

    public function testGetLogsAttemptAsDebug(): void
    {
        $envelope = mock(AMQPEnvelope::class, [
            'getBody' => 'my message body',
            'getDeliveryTag' => 4321,
        ]);
        $this->amqpQueue->setName('my_queue');
        $this->channel->allows()
            ->basicGet('my_queue', false, AMQPQueueException::class, 'AMQPQueue::get')
            ->andReturn($envelope);

        $this->logger->expects()
            ->debug('AMQPQueue::get(): Message fetch attempt (get)', [
                'flags' => AMQP_NOPARAM,
                'queue' => 'my_queue',
            ])
            ->once();

        $this->amqpQueue->get();
    }

    public function testGetFetchesViaChannel(): void
    {
        $envelope = mock(AMQPEnvelope::class, [
            'getBody' => 'my message body',
            'getDeliveryTag' => 4321,
        ]);
        $this->amqpQueue->setName('my_queue');

        $this->channel->expects()
            ->basicGet('my_queue', false, AMQPQueueException::class, 'AMQPQueue::get')
            ->once()
            ->andReturn($envelope);

        $this->amqpQueue->get();
    }

    public function testGetHandlesExceptionCorrectly(): void
    {
        $this->amqpQueue->setName('my_queue');
        $this->channel->allows()
            ->basicGet('my_queue', false, AMQPQueueException::class, 'AMQPQueue::get')
            ->andThrow(new AMQPQueueException('Bang!'));

        $this->expectException(AMQPQueueException::class);
        $this->expectExceptionMessage('Bang!');

        $this->amqpQueue->get();
    }

    public function testGetHandlesMessageFetchCorrectlyWhenOneIsAvailable(): void
    {
        $envelope = mock(AMQPEnvelope::class, [
            'getBody' => 'my message body',
            'getDeliveryTag' => 4321,
        ]);
        $this->amqpQueue->setName('my_queue');
        $this->channel->allows()
            ->basicGet('my_queue', false, AMQPQueueException::class, 'AMQPQueue::get')
            ->andReturn($envelope);

        $this->logger->expects()
            ->debug('AMQPQueue::get(): Message fetched', [
                'body' => 'my message body',
                'delivery_tag' => 4321,
            ])
            ->once();
        static::assertSame($envelope, $this->amqpQueue->get());
    }

    public function testGetHandlesMessageFetchCorrectlyWhenNoneIsAvailable(): void
    {
        $this->amqpQueue->setName('my_queue');
        $this->channel->allows()
            ->basicGet('my_queue', false, AMQPQueueException::class, 'AMQPQueue::get')
            ->andReturnNull();

        $this->logger->expects()
            ->debug('AMQPQueue::get(): No message available, none fetched')
            ->once();
        static::assertFalse($this->amqpQueue->get());
    }

    public function testGetArgumentReturnsTheSpecifiedArgumentValue(): void
    {
        $this->amqpQueue->setArgument('my_key', 'my value');

        static::assertSame('my value', $this->amqpQueue->getArgument('my_key'));
    }

    public function testGetArgumentFalseWhenSpecifiedArgumentIsNotSet(): void
    {
        static::assertFalse($this->amqpQueue->getArgument('an_undefined_key'));
    }

    public function testGetArgumentsReturnsAllArguments(): void
    {
        $this->amqpQueue->setArgument('my_first', 'first value');
        $this->amqpQueue->setArgument('my_second', 'second value');
        $this->amqpQueue->setArgument('my_third', 'third value');

        static::assertEquals(
            [
                'my_first' => 'first value',
                'my_second' => 'second value',
                'my_third' => 'third value',
            ],
            $this->amqpQueue->getArguments()
        );
    }

    public function testGetFlagsReturnsOnlyAutoDeleteInitially(): void
    {
        static::assertSame(AMQP_AUTODELETE, $this->amqpQueue->getFlags());
    }

    public function testHasArgumentReturnsTrueForASetArgument(): void
    {
        $this->amqpQueue->setArgument('my_key', 21);

        static::assertTrue($this->amqpQueue->hasArgument('my_key'));
    }

    public function testHasArgumentReturnsFalseForAnUnsetArgument(): void
    {
        $this->amqpQueue->setArgument('my_key', 21);

        static::assertFalse($this->amqpQueue->hasArgument('not_my_key'));
    }

    public function testNackLogsAttemptAsDebug(): void
    {
        $this->amqpQueue->setName('my_queue');

        $this->logger->expects()
            ->debug('AMQPQueue::nack(): Negative acknowledgement attempt', [
                'delivery_tag' => 123,
                'flags' => AMQP_NOPARAM,
                'queue' => 'my_queue',
            ])
            ->once();

        $this->amqpQueue->nack(123);
    }

    public function testNackNegativelyAcknowledgesViaChannelWithDefaultFlags(): void
    {
        $this->channel->expects()
            ->basicNack(321, false, false, AMQPQueueException::class, 'AMQPQueue::nack')
            ->once();

        $this->amqpQueue->nack(321);
    }

    public function testNackNegativelyAcknowledgesViaChannelWithMultipleFlag(): void
    {
        $this->channel->expects()
            ->basicNack(321, true, false, AMQPQueueException::class, 'AMQPQueue::nack')
            ->once();

        $this->amqpQueue->nack(321, AMQP_MULTIPLE);
    }

    public function testNackNegativelyAcknowledgesViaChannelWithRequeueFlag(): void
    {
        $this->channel->expects()
            ->basicNack(321, false, true, AMQPQueueException::class, 'AMQPQueue::nack')
            ->once();

        $this->amqpQueue->nack(321, AMQP_REQUEUE);
    }

    public function testNackHandlesExceptionCorrectly(): void
    {
        $this->channel->allows()
            ->basicNack(123, false, false, AMQPQueueException::class, 'AMQPQueue::nack')
            ->andThrow(new AMQPQueueException('Bang!'));

        $this->expectException(AMQPQueueException::class);
        $this->expectExceptionMessage('Bang!');

        $this->amqpQueue->nack(123);
    }

    public function testNackReturnsTrue(): void
    {
        static::assertTrue($this->amqpQueue->nack(321));
    }

    public function testNackLogsSuccessAsDebug(): void
    {
        $this->amqpQueue->setName('my_queue');

        $this->logger->expects()
            ->debug('AMQPQueue::nack(): Message negatively acknowledged')
            ->once();

        $this->amqpQueue->nack(123);
    }

    public function testPurgeLogsAttemptAsDebug(): void
    {
        $this->amqpQueue->setName('my_queue');
        $this->channel->allows()
            ->purgeQueue('my_queue', false, AMQPQueueException::class, 'AMQPQueue::purge');

        $this->logger->expects()
            ->debug('AMQPQueue::purge(): Queue messages purge attempt', [
                'queue' => 'my_queue',
            ])
            ->once();

        $this->amqpQueue->purge();
    }

    public function testPurgePurgesQueueViaChannelWithDefaultFlagsSetOnQueue(): void
    {
        $this->amqpQueue->setName('my_queue');

        $this->channel->expects()
            ->purgeQueue('my_queue', false, AMQPQueueException::class, 'AMQPQueue::purge')
            ->once();

        $this->amqpQueue->purge();
    }

    public function testPurgePurgesQueueViaChannelWithNoWaitFlagSetOnQueue(): void
    {
        $this->amqpQueue->setName('my_queue');
        $this->amqpQueue->setFlags(AMQP_NOWAIT);

        $this->channel->expects()
            ->purgeQueue('my_queue', true, AMQPQueueException::class, 'AMQPQueue::purge')
            ->once();

        $this->amqpQueue->purge();
    }

    public function testPurgeHandlesExceptionCorrectly(): void
    {
        $this->amqpQueue->setName('my_queue');
        $this->channel->allows()
            ->purgeQueue('my_queue', false, AMQPQueueException::class, 'AMQPQueue::purge')
            ->andThrow(new AMQPQueueException('Bang!'));

        $this->expectException(AMQPQueueException::class);
        $this->expectExceptionMessage('Bang!');

        $this->amqpQueue->purge();
    }

    public function testPurgeReturnsTrue(): void
    {
        $this->amqpQueue->setName('my_queue');
        $this->channel->allows()
            ->purgeQueue('my_queue', false, AMQPQueueException::class, 'AMQPQueue::purge');

        static::assertTrue($this->amqpQueue->purge());
    }

    public function testPurgeLogsSuccessAsDebug(): void
    {
        $this->amqpQueue->setName('my_queue');
        $this->channel->allows()
            ->purgeQueue('my_queue', false, AMQPQueueException::class, 'AMQPQueue::purge');

        $this->logger->expects()
            ->debug('AMQPQueue::purge(): Queue messages purged')
            ->once();

        $this->amqpQueue->purge();
    }

    public function testSetArgumentThrowsWhenGivenInvalidValue(): void
    {
        $this->expectException(AMQPQueueException::class);
        $this->expectExceptionMessage('The value parameter must be of type NULL, int, double or string.');

        $this->amqpQueue->setArgument('my_key', new stdClass);
    }

    public function testSetArgumentsSetsAllGivenArguments(): void
    {
        $this->amqpQueue->setArguments(['first_key' => 21, 'second_key' => 'my value']);

        static::assertEquals(
            ['first_key' => 21, 'second_key' => 'my value'],
            $this->amqpQueue->getArguments()
        );
    }

    public function testSetArgumentRemovesHeaderWhenGivenValueIsNull(): void
    {
        $this->amqpQueue->setArguments(['first_key' => 21, 'second_key' => 'my value']);

        $this->amqpQueue->setArgument('first_key', null);

        static::assertEquals(
            ['second_key' => 'my value'],
            $this->amqpQueue->getArguments()
        );
        static::assertFalse($this->amqpQueue->hasArgument('first_key'));
    }

    public function testSetFlagsSetsAllGivenFlags(): void
    {
        $this->amqpQueue->setFlags(AMQP_AUTODELETE | AMQP_EXCLUSIVE | AMQP_PASSIVE);

        static::assertSame(AMQP_AUTODELETE | AMQP_EXCLUSIVE | AMQP_PASSIVE, $this->amqpQueue->getFlags());
    }

    public function testSetFlagsCanSetNowaitFlagAlone(): void
    {
        $this->amqpQueue->setFlags(AMQP_NOWAIT);

        static::assertSame(AMQP_NOWAIT, $this->amqpQueue->getFlags());
    }
}
