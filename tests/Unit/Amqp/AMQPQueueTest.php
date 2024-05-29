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
use Asmblah\PhpAmqpCompat\Bridge\Channel\EnvelopeTransformerInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Exception\ExceptionHandlerInterface;
use Asmblah\PhpAmqpCompat\Exception\StopConsumptionException;
use Asmblah\PhpAmqpCompat\Logger\LoggerInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Closure;
use Exception;
use Mockery;
use Mockery\MockInterface;
use PhpAmqpLib\Channel\AMQPChannel as AmqplibChannel;
use PhpAmqpLib\Connection\AbstractConnection as AmqplibConnection;
use PhpAmqpLib\Exception\AMQPIOException;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage as AmqplibMessage;
use PhpAmqpLib\Wire\AMQPTable as AmqplibTable;
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
    private MockInterface&AmqplibChannel $amqplibChannel;
    private MockInterface&AmqplibConnection $amqplibConnection;
    private MockInterface&AmqpChannelBridgeInterface $channelBridge;
    private MockInterface&EnvelopeTransformerInterface $envelopeTransformer;
    private MockInterface&ExceptionHandlerInterface $exceptionHandler;
    private MockInterface&LoggerInterface $logger;

    public function setUp(): void
    {
        $this->amqpChannel = mock(AMQPChannel::class);
        $this->amqplibConnection = mock(AmqplibConnection::class, [
            'isConnected' => true,
        ]);
        $this->amqplibChannel = mock(AmqplibChannel::class, [
            'basic_ack' => null,
            'basic_consume' => 'my_consumer_tag',
            'basic_nack' => null,
            'getConnection' => $this->amqplibConnection,
            'is_open' => true,
        ]);
        $this->envelopeTransformer = mock(EnvelopeTransformerInterface::class);
        $this->exceptionHandler = mock(ExceptionHandlerInterface::class);
        $this->logger = mock(LoggerInterface::class, [
            'debug' => null,
        ]);
        $this->channelBridge = mock(AmqpChannelBridgeInterface::class, [
            'getAmqplibChannel' => $this->amqplibChannel,
            'getEnvelopeTransformer' => $this->envelopeTransformer,
            'getExceptionHandler' => $this->exceptionHandler,
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

        $this->exceptionHandler->allows('handleException')
            ->andReturnUsing(function (
                Exception $libraryException,
                string $exceptionClass,
                string $methodName,
                bool $isConsumption = false
            ) {
                throw new Exception(sprintf(
                    'handleException() :: %s() :: Library Exception<%s> -> %s :: message(%s) isConsumption(%s)',
                    $methodName,
                    $libraryException::class,
                    $exceptionClass,
                    $libraryException->getMessage(),
                    $isConsumption ? 'yes' : 'no'
                ));
            })
            ->byDefault();

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
        $this->amqplibChannel->allows()
            ->basic_ack(123, false);

        $this->logger->expects()
            ->debug('AMQPQueue::ack(): Acknowledgement attempt', [
                'delivery_tag' => 123,
                'flags' => AMQP_NOPARAM,
                'queue' => 'my_queue',
            ])
            ->once();

        /*
         * TODO: Fix whatever is causing PHPStan to wrongly raise a failure here:
         *
         * "phpstan: Parameter #1 $deliveryTag of method AMQPQueue::ack() expects string, int given."
         *
         * @phpstan-ignore-next-line
         */
        $this->amqpQueue->ack(123);
    }

    public function testAckAcknowledgesViaAmqplibWithDefaultFlags(): void
    {
        $this->amqplibChannel->expects()
            ->basic_ack(321, false)
            ->once();

        /*
         * TODO: Fix whatever is causing PHPStan to wrongly raise a failure here:
         *
         * "phpstan: Parameter #1 $deliveryTag of method AMQPQueue::ack() expects string, int given."
         *
         * @phpstan-ignore-next-line
         */
        $this->amqpQueue->ack(321);
    }

    public function testAckAcknowledgesViaAmqplibWithMultipleFlag(): void
    {
        $this->amqplibChannel->expects()
            ->basic_ack(321, true)
            ->once();

        /*
         * TODO: Fix whatever is causing PHPStan to wrongly raise a failure here:
         *
         * "phpstan: Parameter #1 $deliveryTag of method AMQPQueue::ack() expects string, int given."
         *
         * @phpstan-ignore-next-line
         */
        $this->amqpQueue->ack(321, AMQP_MULTIPLE);
    }

    public function testAckHandlesAmqplibExceptionCorrectly(): void
    {
        $exception = new AMQPIOException('Bang!', 123);
        $this->amqplibChannel->allows()
            ->basic_ack(123, false)
            ->andThrow($exception);

        $this->expectExceptionMessage(
            'handleException() :: AMQPQueue::ack() :: ' .
            'Library Exception<PhpAmqpLib\Exception\AMQPIOException> -> AMQPQueueException :: ' .
            'message(Bang!) isConsumption(no)'
        );

        /*
         * TODO: Fix whatever is causing PHPStan to wrongly raise a failure here:
         *
         * "phpstan: Parameter #1 $deliveryTag of method AMQPQueue::ack() expects string, int given."
         *
         * @phpstan-ignore-next-line
         */
        $this->amqpQueue->ack(123);
    }

    public function testAckReturnsTrue(): void
    {
        /*
         * TODO: Fix whatever is causing PHPStan to wrongly raise a failure here:
         *
         * "phpstan: Parameter #1 $deliveryTag of method AMQPQueue::ack() expects string, int given."
         *
         * @phpstan-ignore-next-line
         */
        static::assertTrue($this->amqpQueue->ack(321));
    }

    public function testAckLogsSuccessAsDebug(): void
    {
        $this->amqpQueue->setName('my_queue');
        $this->amqplibChannel->allows()
            ->basic_ack(123, false);

        $this->logger->expects()
            ->debug('AMQPQueue::ack(): Message acknowledged')
            ->once();

        /*
         * TODO: Fix whatever is causing PHPStan to wrongly raise a failure here:
         *
         * "phpstan: Parameter #1 $deliveryTag of method AMQPQueue::ack() expects string, int given."
         *
         * @phpstan-ignore-next-line
         */
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
        $this->amqplibChannel->allows('wait')
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
        $this->amqplibChannel->allows('wait')
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
        $this->amqplibChannel->allows()
            ->basic_consume(
                'my_queue',
                'my_input_consumer_tag',
                false,
                false,
                false,
                false,
                Mockery::type(Closure::class),
                null,
                []
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
        $this->amqplibChannel->allows('wait')
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
        $consumerCallback = null;
        $this->amqpQueue->setName('my_queue');
        $this->amqplibChannel->allows()
            ->basic_consume(
                'my_queue',
                'my_input_consumer_tag',
                false,
                false,
                false,
                false,
                Mockery::type(Closure::class),
                null,
                []
            )
            ->andReturnUsing(function (
                $queue,
                $consumerTag,
                $noLocal,
                $noAck,
                $exclusive,
                $noWait,
                callable $callback
            ) use (&$consumerCallback) {
                $consumerCallback = $callback;

                return 'my_consumer_tag';
            });
        $amqplibMessage = mock(AmqplibMessage::class, [
            'getConsumerTag' => 'my_consumer_tag',
        ]);
        $amqpEnvelope = mock(AMQPEnvelope::class);
        $this->envelopeTransformer->allows()
            ->transformMessage($amqplibMessage)
            ->andReturn($amqpEnvelope);

        $this->channelBridge->expects()
            ->consumeEnvelope($amqpEnvelope)
            ->once();

        $this->amqpQueue->consume(null, AMQP_NOPARAM, 'my_input_consumer_tag');
        $consumerCallback($amqplibMessage);
    }

    public function testConsumeWithJustConsumeFlagProvidesCallbackToChannelBridge(): void
    {
        $consumerCallback = function () {};
        $this->amqpQueue->setName('my_queue');
        $this->amqplibChannel->allows('wait')
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
        $this->amqplibChannel->allows('wait')
            ->andThrow(new StopConsumptionException());

        $this->amqplibChannel->expects('basic_consume')
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
        $consumerCallback = null;
        $this->amqpQueue->setName('my_queue');
        $this->amqplibChannel->allows()
            ->basic_consume(
                'my_queue',
                'my_input_consumer_tag',
                false,
                false,
                false,
                false,
                Mockery::type(Closure::class),
                null,
                []
            )
            ->andReturnUsing(function (
                $queue,
                $consumerTag,
                $noLocal,
                $noAck,
                $exclusive,
                $noWait,
                callable $callback
            ) use (&$consumerCallback) {
                $consumerCallback = $callback;

                return 'my_consumer_tag';
            });
        $amqplibMessage = mock(AmqplibMessage::class, [
            'getConsumerTag' => 'my_unknown_consumer_tag',
        ]);
        $amqpEnvelope = mock(AMQPEnvelope::class);
        $this->envelopeTransformer->allows()
            ->transformMessage($amqplibMessage)
            ->andReturn($amqpEnvelope);
        $this->channelBridge->expects()
            ->isConsumerSubscribed('my_unknown_consumer_tag')
            ->andReturnFalse();

        $this->expectException(AMQPEnvelopeException::class);
        $this->expectExceptionMessage('Orphaned envelope');
        $this->amqpQueue->consume(null, AMQP_NOPARAM, 'my_input_consumer_tag');
        try {
            $consumerCallback($amqplibMessage);
        } catch (AMQPEnvelopeException $exception) {
            static::assertSame($amqpEnvelope, $exception->envelope);
            throw $exception;
        }
    }

    public function testConsumeHandlesAmqplibExceptionCorrectly(): void
    {
        $this->amqpQueue->setName('my_queue');
        $consumerCallback = function () {};
        $exception = new AMQPIOException('Bang!', 123);
        $this->amqplibChannel->allows()
            ->basic_consume(
                'my_queue',
                'my_input_consumer_tag',
                false,
                false,
                false,
                false,
                Mockery::type(Closure::class),
                null,
                []
            )
            ->andThrow($exception);

        $this->expectExceptionMessage(
            'handleException() :: AMQPQueue::consume() :: ' .
            'Library Exception<PhpAmqpLib\Exception\AMQPIOException> -> AMQPQueueException :: ' .
            'message(Bang!) isConsumption(no)'
        );

        $this->amqpQueue->consume($consumerCallback, AMQP_NOPARAM, 'my_input_consumer_tag');
    }

    public function testConsumeWaitsUpToConfiguredReadTimeout(): void
    {
        $consumerCallback = function () {};
        $this->amqpQueue->setName('my_queue');

        $this->amqplibChannel->expects()
            ->wait(null, false, 12)
            ->once()
            ->andThrow(new StopConsumptionException());

        $this->amqpQueue->consume($consumerCallback);
    }

    public function testConsumeHandlesExceptionsDuringWaitViaExceptionHandler(): void
    {
        $consumerCallback = function () {};
        $this->amqpQueue->setName('my_queue');
        $this->amqplibChannel->allows('wait')
            ->andThrow(new AMQPTimeoutException('Bang!'));

        $this->expectExceptionMessage(
            'handleException() :: AMQPQueue::consume() :: ' .
            'Library Exception<PhpAmqpLib\Exception\AMQPTimeoutException> -> AMQPQueueException :: ' .
            'message(Bang!) isConsumption(yes)'
        );

        $this->amqpQueue->consume($consumerCallback);
    }

    public function testDeclareQueueDeclaresViaAmqplib(): void
    {
        $this->amqpQueue->setName('my_queue');
        $this->amqpQueue->setFlags(AMQP_PASSIVE | AMQP_EXCLUSIVE | AMQP_AUTODELETE);
        $this->amqpQueue->setArguments(['x-dead-letter-exchange' => 'my_retry_exchange']);

        $this->amqplibChannel->expects()
            ->queue_declare(
                'my_queue',
                true,
                false,
                true,
                true,
                false,
                Mockery::type(AmqplibTable::class)
            )
            ->once()
            ->andReturnUsing(function (
                $queueName,
                $passive,
                $durable,
                $exclusive,
                $autoDelete,
                $noWait,
                AmqplibTable $arguments
            ) {
                static::assertEquals(
                    ['x-dead-letter-exchange' => 'my_retry_exchange'],
                    $arguments->getNativeData()
                );

                $queueName = 'my_queue';
                $messageCount = 21;
                $consumerCount = 7;

                return [$queueName, $messageCount, $consumerCount];
            });

        static::assertSame(21, $this->amqpQueue->declareQueue(), 'Message count should be returned');
    }

    public function testDeclareQueueStoresReturnedQueueName(): void
    {
        $this->amqpQueue->setFlags(AMQP_PASSIVE | AMQP_EXCLUSIVE | AMQP_AUTODELETE);
        $this->amqpQueue->setArguments(['x-dead-letter-exchange' => 'my_retry_exchange']);
        $this->amqplibChannel->allows()
            ->queue_declare(
                '',
                true,
                false,
                true,
                true,
                false,
                Mockery::type(AmqplibTable::class)
            )
            ->andReturnUsing(function () {
                $queueName = 'my_generated_queue';
                $messageCount = 21;
                $consumerCount = 7;

                return [$queueName, $messageCount, $consumerCount];
            });

        $this->amqpQueue->declareQueue();

        static::assertSame(
            'my_generated_queue',
            $this->amqpQueue->getName(),
            'Returned queue name should be used'
        );
    }

    public function testDeclareQueueHandlesAmqplibExceptionCorrectly(): void
    {
        $this->amqpQueue->setName('my_queue');
        $exception = new AMQPProtocolChannelException(21, 'my text', [1, 2, 3]);

        $this->amqplibChannel->allows()
            ->queue_declare(
                'my_queue',
                false,
                false,
                false,
                true,
                false,
                Mockery::type(AmqplibTable::class)
            )
            ->andThrow($exception);

        $this->expectExceptionMessage(
            'handleException() :: AMQPQueue::declareQueue() :: ' .
            'Library Exception<PhpAmqpLib\Exception\AMQPProtocolChannelException> -> AMQPQueueException :: ' .
            'message(my text) isConsumption(no)'
        );

        $this->amqpQueue->declareQueue();
    }

    public function testDeclareQueueHandlesNonArrayResultFromAmqplibCorrectly(): void
    {
        $this->amqpQueue->setName('my_queue');

        $this->amqplibChannel->allows()
            ->queue_declare(
                'my_queue',
                false,
                false,
                false,
                true,
                false,
                Mockery::type(AmqplibTable::class)
            )
            ->andReturn('I should be an array');

        $this->expectException(AMQPQueueException::class);
        $this->expectExceptionMessage('AMQPQueue::declareQueue(): Amqplib result was not an array');

        $this->amqpQueue->declareQueue();
    }

    public function testDeclareQueueHandlesInvalidArrayResultFromAmqplibCorrectly(): void
    {
        $this->amqpQueue->setName('my_queue');

        $this->amqplibChannel->allows()
            ->queue_declare(
                'my_queue',
                false,
                false,
                false,
                true,
                false,
                Mockery::type(AmqplibTable::class)
            )
            ->andReturn(['I am not valid']);

        $this->expectException(AMQPQueueException::class);
        $this->expectExceptionMessage(
            'AMQPQueue::declareQueue(): Amqplib result should contain message count at [1]'
        );

        $this->amqpQueue->declareQueue();
    }

    public function testDeleteLogsAttemptAsDebug(): void
    {
        $this->amqpQueue->setName('my_queue');
        $this->amqplibChannel->allows()
            ->queue_delete('my_queue', false, false, false)
            ->andReturn(0);

        $this->logger->expects()
            ->debug('AMQPQueue::delete(): Queue deletion attempt', [
                'flags' => AMQP_NOPARAM,
                'queue' => 'my_queue',
            ])
            ->once();

        $this->amqpQueue->delete();
    }

    public function testDeleteDeletesQueueViaAmqplibWithDefaultFlags(): void
    {
        $this->amqpQueue->setName('my_queue');

        $this->amqplibChannel->expects()
            ->queue_delete('my_queue', false, false, false)
            ->once();

        $this->amqpQueue->delete();
    }

    public function testDeleteDeletesQueueViaAmqplibWithIfUnusedFlag(): void
    {
        $this->amqpQueue->setName('my_queue');

        $this->amqplibChannel->expects()
            ->queue_delete('my_queue', true, false, false)
            ->once();

        $this->amqpQueue->delete(AMQP_IFUNUSED);
    }

    public function testDeleteDeletesQueueViaAmqplibWithIfEmptyFlag(): void
    {
        $this->amqpQueue->setName('my_queue');

        $this->amqplibChannel->expects()
            ->queue_delete('my_queue', false, true, false)
            ->once();

        $this->amqpQueue->delete(AMQP_IFEMPTY);
    }

    public function testDeleteDeletesQueueViaAmqplibWithNoWaitFlagSetOnQueue(): void
    {
        $this->amqpQueue->setName('my_queue');
        $this->amqpQueue->setFlags(AMQP_NOWAIT);

        $this->amqplibChannel->expects()
            ->queue_delete('my_queue', false, false, true)
            ->once();

        $this->amqpQueue->delete();
    }

    public function testDeleteHandlesAmqplibExceptionCorrectly(): void
    {
        $this->amqpQueue->setName('my_queue');
        $exception = new AMQPIOException('Bang!', 123);
        $this->amqplibChannel->allows()
            ->queue_delete('my_queue', false, false, false)
            ->andThrow($exception);

        $this->expectExceptionMessage(
            'handleException() :: AMQPQueue::delete() :: ' .
            'Library Exception<PhpAmqpLib\Exception\AMQPIOException> -> AMQPQueueException :: ' .
            'message(Bang!) isConsumption(no)'
        );

        $this->amqpQueue->delete();
    }

    public function testDeleteReturnsTheNumberOfMessagesThatWereInTheDeletedQueue(): void
    {
        $this->amqpQueue->setName('my_queue');
        $this->amqplibChannel->allows()
            ->queue_delete('my_queue', false, false, false)
            ->andReturn(21);

        static::assertSame(21, $this->amqpQueue->delete());
    }

    public function testDeleteLogsSuccessAsDebug(): void
    {
        $this->amqpQueue->setName('my_queue');
        $this->amqplibChannel->allows()
            ->queue_delete('my_queue', false, false, false);

        $this->logger->expects()
            ->debug('AMQPQueue::delete(): Queue deleted')
            ->once();

        $this->amqpQueue->delete();
    }

    public function testGetLogsAttemptAsDebug(): void
    {
        $amqplibMessage = mock(AmqplibMessage::class, [
            'getBody' => 'my message body',
            'getDeliveryTag' => 4321,
        ]);
        $this->amqpQueue->setName('my_queue');
        $this->amqplibChannel->allows()
            ->basic_get('my_queue', false)
            ->andReturn($amqplibMessage);
        $envelope = mock(AMQPEnvelope::class);
        $this->envelopeTransformer->allows()
            ->transformMessage($amqplibMessage)
            ->andReturn($envelope);

        $this->logger->expects()
            ->debug('AMQPQueue::get(): Message fetch attempt (get)', [
                'flags' => AMQP_NOPARAM,
                'queue' => 'my_queue',
            ])
            ->once();

        $this->amqpQueue->get();
    }

    public function testGetFetchesViaAmqplib(): void
    {
        $amqplibMessage = mock(AmqplibMessage::class, [
            'getBody' => 'my message body',
            'getDeliveryTag' => 4321,
        ]);
        $this->amqpQueue->setName('my_queue');
        $envelope = mock(AMQPEnvelope::class);
        $this->envelopeTransformer->allows()
            ->transformMessage($amqplibMessage)
            ->andReturn($envelope);

        $this->amqplibChannel->expects()
            ->basic_get('my_queue', false)
            ->once()
            ->andReturn($amqplibMessage);

        $this->amqpQueue->get();
    }

    public function testGetHandlesAmqplibExceptionCorrectly(): void
    {
        $exception = new AMQPIOException('Bang!', 123);
        $this->amqpQueue->setName('my_queue');
        $this->amqplibChannel->allows()
            ->basic_get('my_queue', false)
            ->andThrow($exception);

        $this->expectExceptionMessage(
            'handleException() :: AMQPQueue::get() :: ' .
            'Library Exception<PhpAmqpLib\Exception\AMQPIOException> -> AMQPQueueException :: ' .
            'message(Bang!) isConsumption(no)'
        );

        $this->amqpQueue->get();
    }

    public function testGetHandlesMessageFetchCorrectlyWhenOneIsAvailable(): void
    {
        $amqplibMessage = mock(AmqplibMessage::class, [
            'getBody' => 'my message body',
            'getDeliveryTag' => 4321,
        ]);
        $this->amqpQueue->setName('my_queue');
        $this->amqplibChannel->allows()
            ->basic_get('my_queue', false)
            ->andReturn($amqplibMessage);
        $envelope = mock(AMQPEnvelope::class);
        $this->envelopeTransformer->allows()
            ->transformMessage($amqplibMessage)
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
        $this->amqplibChannel->allows()
            ->basic_get('my_queue', false)
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
        $this->amqplibChannel->allows()
            ->basic_nack(123, false, false);

        $this->logger->expects()
            ->debug('AMQPQueue::nack(): Negative acknowledgement attempt', [
                'delivery_tag' => 123,
                'flags' => AMQP_NOPARAM,
                'queue' => 'my_queue',
            ])
            ->once();

        /*
         * TODO: Fix whatever is causing PHPStan to wrongly raise a failure here:
         *
         * "phpstan: Parameter #1 $deliveryTag of method AMQPQueue::nack() expects string, int given."
         *
         * @phpstan-ignore-next-line
         */
        $this->amqpQueue->nack(123);
    }

    public function testNackNegativelyAcknowledgesViaAmqplibWithDefaultFlags(): void
    {
        $this->amqplibChannel->expects()
            ->basic_nack(321, false, false)
            ->once();

        /*
         * TODO: Fix whatever is causing PHPStan to wrongly raise a failure here:
         *
         * "phpstan: Parameter #1 $deliveryTag of method AMQPQueue::nack() expects string, int given."
         *
         * @phpstan-ignore-next-line
         */
        $this->amqpQueue->nack(321);
    }

    public function testNackNegativelyAcknowledgesViaAmqplibWithMultipleFlag(): void
    {
        $this->amqplibChannel->expects()
            ->basic_nack(321, true, false)
            ->once();

        /*
         * TODO: Fix whatever is causing PHPStan to wrongly raise a failure here:
         *
         * "phpstan: Parameter #1 $deliveryTag of method AMQPQueue::nack() expects string, int given."
         *
         * @phpstan-ignore-next-line
         */
        $this->amqpQueue->nack(321, AMQP_MULTIPLE);
    }

    public function testNackNegativelyAcknowledgesViaAmqplibWithRequeueFlag(): void
    {
        $this->amqplibChannel->expects()
            ->basic_nack(321, false, true)
            ->once();

        /*
         * TODO: Fix whatever is causing PHPStan to wrongly raise a failure here:
         *
         * "phpstan: Parameter #1 $deliveryTag of method AMQPQueue::nack() expects string, int given."
         *
         * @phpstan-ignore-next-line
         */
        $this->amqpQueue->nack(321, AMQP_REQUEUE);
    }

    public function testNackHandlesAmqplibExceptionCorrectly(): void
    {
        $exception = new AMQPIOException('Bang!', 123);
        $this->amqplibChannel->allows()
            ->basic_nack(123, false, false)
            ->andThrow($exception);

        $this->expectExceptionMessage(
            'handleException() :: AMQPQueue::nack() :: ' .
            'Library Exception<PhpAmqpLib\Exception\AMQPIOException> -> AMQPQueueException :: ' .
            'message(Bang!) isConsumption(no)'
        );

        /*
         * TODO: Fix whatever is causing PHPStan to wrongly raise a failure here:
         *
         * "phpstan: Parameter #1 $deliveryTag of method AMQPQueue::nack() expects string, int given."
         *
         * @phpstan-ignore-next-line
         */
        $this->amqpQueue->nack(123);
    }

    public function testNackReturnsTrue(): void
    {
        /*
         * TODO: Fix whatever is causing PHPStan to wrongly raise a failure here:
         *
         * "phpstan: Parameter #1 $deliveryTag of method AMQPQueue::nack() expects string, int given."
         *
         * @phpstan-ignore-next-line
         */
        static::assertTrue($this->amqpQueue->nack(321));
    }

    public function testNackLogsSuccessAsDebug(): void
    {
        $this->amqpQueue->setName('my_queue');
        $this->amqplibChannel->allows()
            ->basic_nack(123, false, false);

        $this->logger->expects()
            ->debug('AMQPQueue::nack(): Message negatively acknowledged')
            ->once();

        /*
         * TODO: Fix whatever is causing PHPStan to wrongly raise a failure here:
         *
         * "phpstan: Parameter #1 $deliveryTag of method AMQPQueue::nack() expects string, int given."
         *
         * @phpstan-ignore-next-line
         */
        $this->amqpQueue->nack(123);
    }

    public function testPurgeLogsAttemptAsDebug(): void
    {
        $this->amqpQueue->setName('my_queue');
        $this->amqplibChannel->allows()
            ->queue_purge('my_queue', false);

        $this->logger->expects()
            ->debug('AMQPQueue::purge(): Queue messages purge attempt', [
                'queue' => 'my_queue',
            ])
            ->once();

        $this->amqpQueue->purge();
    }

    public function testPurgePurgesQueueViaAmqplibWithDefaultFlagsSetOnQueue(): void
    {
        $this->amqpQueue->setName('my_queue');

        $this->amqplibChannel->expects()
            ->queue_purge('my_queue', false)
            ->once();

        $this->amqpQueue->purge();
    }

    public function testPurgePurgesQueueViaAmqplibWithNoWaitFlagSetOnQueue(): void
    {
        $this->amqpQueue->setName('my_queue');
        $this->amqpQueue->setFlags(AMQP_NOWAIT);

        $this->amqplibChannel->expects()
            ->queue_purge('my_queue', true)
            ->once();

        $this->amqpQueue->purge();
    }

    public function testPurgeHandlesAmqplibExceptionCorrectly(): void
    {
        $this->amqpQueue->setName('my_queue');
        $exception = new AMQPIOException('Bang!', 123);
        $this->amqplibChannel->allows()
            ->queue_purge('my_queue', false)
            ->andThrow($exception);

        $this->expectExceptionMessage(
            'handleException() :: AMQPQueue::purge() :: ' .
            'Library Exception<PhpAmqpLib\Exception\AMQPIOException> -> AMQPQueueException :: ' .
            'message(Bang!) isConsumption(no)'
        );

        $this->amqpQueue->purge();
    }

    public function testPurgeReturnsTrue(): void
    {
        $this->amqpQueue->setName('my_queue');
        $this->amqplibChannel->allows()
            ->queue_purge('my_queue', false);

        static::assertTrue($this->amqpQueue->purge());
    }

    public function testPurgeLogsSuccessAsDebug(): void
    {
        $this->amqpQueue->setName('my_queue');
        $this->amqplibChannel->allows()
            ->queue_purge('my_queue', false);

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
