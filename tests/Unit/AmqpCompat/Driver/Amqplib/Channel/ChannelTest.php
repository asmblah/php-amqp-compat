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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\AmqpCompat\Driver\Amqplib\Channel;

use AMQPChannelException;
use AMQPEnvelope;
use AMQPExchangeException;
use AMQPQueueException;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Channel\Channel;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transformer\EnvelopeTransformerInterface;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transformer\MessageTransformerInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Exception\ExceptionHandlerInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Transport\TransportInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Closure;
use Mockery;
use Mockery\MockInterface;
use PhpAmqpLib\Channel\AMQPChannel as AmqplibChannel;
use PhpAmqpLib\Connection\AbstractConnection as AmqplibConnection;
use PhpAmqpLib\Exception\AMQPLogicException;
use PhpAmqpLib\Message\AMQPMessage as AmqplibMessage;
use PhpAmqpLib\Wire\AMQPTable as AmqplibTable;

/**
 * Class ChannelTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ChannelTest extends AbstractTestCase
{
    private MockInterface&AmqplibChannel $amqplibChannel;
    private Channel $channel;
    private MockInterface&EnvelopeTransformerInterface $envelopeTransformer;
    private MockInterface&ExceptionHandlerInterface $exceptionHandler;
    private MockInterface&MessageTransformerInterface $messageTransformer;
    private MockInterface&TransportInterface $transport;

    public function setUp(): void
    {
        $this->amqplibChannel = mock(AmqplibChannel::class);
        $this->envelopeTransformer = mock(EnvelopeTransformerInterface::class);
        $this->exceptionHandler = mock(ExceptionHandlerInterface::class);
        $this->messageTransformer = mock(MessageTransformerInterface::class);
        $this->transport = mock(TransportInterface::class);

        $this->channel = new Channel(
            $this->transport,
            $this->amqplibChannel,
            $this->exceptionHandler,
            $this->envelopeTransformer,
            $this->messageTransformer
        );
    }

    /**
     * @dataProvider booleanDataProvider
     */
    public function testBasicAckPassesMultipleThroughToAmqplib(bool $multiple): void
    {
        $this->amqplibChannel->expects()
            ->basic_ack(4321, $multiple)
            ->once();

        $this->channel->basicAck(4321, $multiple, AMQPQueueException::class, 'AMQPQueue::ack');
    }

    public function testBasicAckPassesExceptionToHandlerOnAmqplibFailure(): void
    {
        $exception = new AMQPLogicException('Bang!');
        $this->amqplibChannel->allows('basic_ack')
            ->andThrow($exception);

        $this->exceptionHandler->expects()
            ->handleException($exception, AMQPQueueException::class, 'AMQPQueue::ack')
            ->once()
            ->andThrow(new AMQPQueueException('Bang!'));

        $this->expectException(AMQPQueueException::class);
        $this->expectExceptionMessage('Bang!');

        $this->channel->basicAck(4321, false, AMQPQueueException::class, 'AMQPQueue::ack');
    }

    /**
     * @dataProvider booleanDataProvider
     */
    public function testBasicCancelPassesNoWaitThroughToAmqplib(bool $noWait): void
    {
        $this->amqplibChannel->expects()
            ->basic_cancel('my-consumer-tag', $noWait)
            ->once();

        $this->channel->basicCancel('my-consumer-tag', $noWait, AMQPQueueException::class, 'AMQPQueue::cancel');
    }

    public function testBasicCancelPassesExceptionToHandlerOnAmqplibFailure(): void
    {
        $exception = new AMQPLogicException('Bang!');
        $this->amqplibChannel->allows('basic_cancel')
            ->andThrow($exception);

        $this->exceptionHandler->expects()
            ->handleException($exception, AMQPQueueException::class, 'AMQPQueue::cancel')
            ->once()
            ->andThrow(new AMQPQueueException('Bang!'));

        $this->expectException(AMQPQueueException::class);
        $this->expectExceptionMessage('Bang!');

        $this->channel->basicCancel('my-consumer-tag', false, AMQPQueueException::class, 'AMQPQueue::cancel');
    }

    /**
     * @dataProvider booleanDataProvider
     */
    public function testBasicConsumePassesNoLocalThroughToAmqplib(bool $noLocal): void
    {
        $this->amqplibChannel->expects()
            ->basic_consume('my_queue', '', $noLocal, false, false, false, Mockery::type(Closure::class))
            ->once()
            ->andReturn('my-tag');

        $this->channel->basicConsume(
            'my_queue',
            '',
            $noLocal,
            false,
            false,
            static function () {},
            AMQPQueueException::class,
            'AMQPQueue::consume'
        );
    }

    /**
     * @dataProvider booleanDataProvider
     */
    public function testBasicConsumePassesAutoAckThroughToAmqplib(bool $autoAck): void
    {
        $this->amqplibChannel->expects()
            ->basic_consume('my_queue', '', false, $autoAck, false, false, Mockery::type(Closure::class))
            ->once()
            ->andReturn('my-tag');

        $this->channel->basicConsume(
            'my_queue',
            '',
            false,
            $autoAck,
            false,
            static function () {},
            AMQPQueueException::class,
            'AMQPQueue::consume'
        );
    }

    /**
     * @dataProvider booleanDataProvider
     */
    public function testBasicConsumePassesExclusiveThroughToAmqplib(bool $exclusive): void
    {
        $this->amqplibChannel->expects()
            ->basic_consume('my_queue', '', false, false, $exclusive, false, Mockery::type(Closure::class))
            ->once()
            ->andReturn('my-tag');

        $this->channel->basicConsume(
            'my_queue',
            '',
            false,
            false,
            $exclusive,
            static function () {},
            AMQPQueueException::class,
            'AMQPQueue::consume'
        );
    }

    public function testBasicConsumeUsesHardcodedNoWaitFalseWhenCallingAmqplib(): void
    {
        $this->amqplibChannel->expects()
            ->basic_consume('my_queue', '', false, false, false, false, Mockery::type(Closure::class))
            ->once()
            ->andReturn('my-tag');

        $this->channel->basicConsume(
            'my_queue',
            '',
            false,
            false,
            false,
            static function () {},
            AMQPQueueException::class,
            'AMQPQueue::consume'
        );
    }

    public function testBasicConsumeReturnsConsumerTagFromAmqplib(): void
    {
        $this->amqplibChannel->allows('basic_consume')
            ->andReturn('returned-consumer-tag');

        $result = $this->channel->basicConsume(
            'my_queue',
            '',
            false,
            false,
            false,
            static function () {},
            AMQPQueueException::class,
            'AMQPQueue::consume'
        );

        static::assertSame('returned-consumer-tag', $result);
    }

    public function testBasicConsumeTransformsAmqplibMessageViaEnvelopeTransformerBeforeCallingCallback(): void
    {
        $amqplibMessage = mock(AmqplibMessage::class);
        $amqpEnvelope = mock(AMQPEnvelope::class);
        $capturedCallback = null;
        $this->amqplibChannel->allows('basic_consume')
            ->andReturnUsing(function (
                string $queue,
                string $tag,
                bool $noLocal,
                bool $noAck,
                bool $exclusive,
                bool $noWait,
                callable $callback
            ) use (&$capturedCallback) {
                $capturedCallback = $callback;

                return 'my-tag';
            });
        $this->envelopeTransformer->allows()
            ->transformMessage($amqplibMessage)
            ->andReturn($amqpEnvelope);
        /** @var AMQPEnvelope[] $receivedEnvelopes */
        $receivedEnvelopes = [];

        $this->channel->basicConsume(
            'my_queue',
            '',
            false,
            false,
            false,
            function (AMQPEnvelope $envelope) use (&$receivedEnvelopes) {
                $receivedEnvelopes[] = $envelope;
            },
            AMQPQueueException::class,
            'AMQPQueue::consume'
        );
        $capturedCallback($amqplibMessage);

        static::assertCount(1, $receivedEnvelopes);
        static::assertSame($amqpEnvelope, $receivedEnvelopes[0]);
    }

    public function testBasicConsumePassesExceptionToHandlerOnAmqplibFailure(): void
    {
        $exception = new AMQPLogicException('Bang!');
        $this->amqplibChannel->allows('basic_consume')
            ->andThrow($exception);

        $this->exceptionHandler->expects()
            ->handleException($exception, AMQPQueueException::class, 'AMQPQueue::consume')
            ->once()
            ->andThrow(new AMQPQueueException('Bang!'));

        $this->expectException(AMQPQueueException::class);
        $this->expectExceptionMessage('Bang!');

        $this->channel->basicConsume(
            'my_queue',
            '',
            false,
            false,
            false,
            static function () {},
            AMQPQueueException::class,
            'AMQPQueue::consume'
        );
    }

    public function testBasicGetReturnsNullWhenQueueIsEmpty(): void
    {
        $this->amqplibChannel->allows()
            ->basic_get('my_queue', false)
            ->andReturnNull();

        $result = $this->channel->basicGet('my_queue', false, AMQPQueueException::class, 'AMQPQueue::get');

        static::assertNull($result);
    }

    public function testBasicGetReturnsTransformedEnvelopeWhenMessageIsAvailable(): void
    {
        $amqplibMessage = mock(AmqplibMessage::class);
        $amqpEnvelope = mock(AMQPEnvelope::class);
        $this->amqplibChannel->allows()
            ->basic_get('my_queue', false)
            ->andReturn($amqplibMessage);
        $this->envelopeTransformer->allows()
            ->transformMessage($amqplibMessage)
            ->andReturn($amqpEnvelope);

        $result = $this->channel->basicGet('my_queue', false, AMQPQueueException::class, 'AMQPQueue::get');

        static::assertSame($amqpEnvelope, $result);
    }

    /**
     * @dataProvider booleanDataProvider
     */
    public function testBasicGetPassesAutoAckThroughToAmqplib(bool $autoAck): void
    {
        $this->amqplibChannel->expects()
            ->basic_get('my_queue', $autoAck)
            ->once()
            ->andReturnNull();

        $this->channel->basicGet('my_queue', $autoAck, AMQPQueueException::class, 'AMQPQueue::get');
    }

    public function testBasicGetPassesExceptionToHandlerOnAmqplibFailure(): void
    {
        $exception = new AMQPLogicException('Bang!');
        $this->amqplibChannel->allows('basic_get')
            ->andThrow($exception);

        $this->exceptionHandler->expects()
            ->handleException($exception, AMQPQueueException::class, 'AMQPQueue::get')
            ->once()
            ->andThrow(new AMQPQueueException('Bang!'));

        $this->expectException(AMQPQueueException::class);
        $this->expectExceptionMessage('Bang!');

        $this->channel->basicGet('my_queue', false, AMQPQueueException::class, 'AMQPQueue::get');
    }

    /**
     * @dataProvider booleanDataProvider
     */
    public function testBasicNackPassesMultipleThroughToAmqplib(bool $multiple): void
    {
        $this->amqplibChannel->expects()
            ->basic_nack(4321, $multiple, false)
            ->once();

        $this->channel->basicNack(4321, $multiple, false, AMQPQueueException::class, 'AMQPQueue::nack');
    }

    /**
     * @dataProvider booleanDataProvider
     */
    public function testBasicNackPassesRequeueThroughToAmqplib(bool $requeue): void
    {
        $this->amqplibChannel->expects()
            ->basic_nack(4321, false, $requeue)
            ->once();

        $this->channel->basicNack(4321, false, $requeue, AMQPQueueException::class, 'AMQPQueue::nack');
    }

    public function testBasicNackPassesExceptionToHandlerOnAmqplibFailure(): void
    {
        $exception = new AMQPLogicException('Bang!');
        $this->amqplibChannel->allows('basic_nack')
            ->andThrow($exception);

        $this->exceptionHandler->expects()
            ->handleException($exception, AMQPQueueException::class, 'AMQPQueue::nack')
            ->once()
            ->andThrow(new AMQPQueueException('Bang!'));

        $this->expectException(AMQPQueueException::class);
        $this->expectExceptionMessage('Bang!');

        $this->channel->basicNack(4321, false, false, AMQPQueueException::class, 'AMQPQueue::nack');
    }

    public function testBasicPublishTransformsMessageAndPublishesToAmqplib(): void
    {
        $amqplibMessage = mock(AmqplibMessage::class);
        $attributes = ['content_type' => 'text/plain'];
        $this->messageTransformer->allows()
            ->transformEnvelope('my message', $attributes)
            ->andReturn($amqplibMessage);

        $this->amqplibChannel->expects()
            ->basic_publish($amqplibMessage, 'my_exchange', 'my_routing_key', false, false)
            ->once();

        $this->channel->basicPublish(
            'my message',
            $attributes,
            'my_exchange',
            'my_routing_key',
            false,
            false,
            AMQPExchangeException::class,
            'AMQPExchange::publish'
        );
    }

    /**
     * @dataProvider booleanDataProvider
     */
    public function testBasicPublishPassesMandatoryThroughToAmqplib(bool $mandatory): void
    {
        $amqplibMessage = mock(AmqplibMessage::class);
        $this->messageTransformer->allows('transformEnvelope')
            ->andReturn($amqplibMessage);

        $this->amqplibChannel->expects()
            ->basic_publish($amqplibMessage, 'my_exchange', 'my_routing_key', $mandatory, false)
            ->once();

        $this->channel->basicPublish(
            'my message',
            [],
            'my_exchange',
            'my_routing_key',
            $mandatory,
            false,
            AMQPExchangeException::class,
            'AMQPExchange::publish'
        );
    }

    /**
     * @dataProvider booleanDataProvider
     */
    public function testBasicPublishPassesImmediateThroughToAmqplib(bool $immediate): void
    {
        $amqplibMessage = mock(AmqplibMessage::class);
        $this->messageTransformer->allows('transformEnvelope')
            ->andReturn($amqplibMessage);

        $this->amqplibChannel->expects()
            ->basic_publish($amqplibMessage, 'my_exchange', 'my_routing_key', false, $immediate)
            ->once();

        $this->channel->basicPublish(
            'my message',
            [],
            'my_exchange',
            'my_routing_key',
            false,
            $immediate,
            AMQPExchangeException::class,
            'AMQPExchange::publish'
        );
    }

    public function testBasicPublishPassesExceptionToHandlerOnAmqplibFailure(): void
    {
        $exception = new AMQPLogicException('Bang!');
        $this->messageTransformer->allows('transformEnvelope')
            ->andReturn(mock(AmqplibMessage::class));
        $this->amqplibChannel->allows('basic_publish')
            ->andThrow($exception);

        $this->exceptionHandler->expects()
            ->handleException($exception, AMQPExchangeException::class, 'AMQPExchange::publish')
            ->once()
            ->andThrow(new AMQPExchangeException('Bang!'));

        $this->expectException(AMQPExchangeException::class);
        $this->expectExceptionMessage('Bang!');

        $this->channel->basicPublish(
            'my message',
            [],
            'my_exchange',
            'my_routing_key',
            false,
            false,
            AMQPExchangeException::class,
            'AMQPExchange::publish'
        );
    }

    /**
     * @dataProvider booleanDataProvider
     */
    public function testBasicQosPassesGlobalThroughToAmqplib(bool $global): void
    {
        $this->amqplibChannel->expects()
            ->basic_qos(128, 10, $global)
            ->once();

        $this->channel->basicQos(128, 10, $global, AMQPChannelException::class, 'AMQPChannel::qos');
    }

    public function testBasicQosPassesExceptionToHandlerOnAmqplibFailure(): void
    {
        $exception = new AMQPLogicException('Bang!');
        $this->amqplibChannel->allows('basic_qos')
            ->andThrow($exception);

        $this->exceptionHandler->expects()
            ->handleException($exception, AMQPChannelException::class, 'AMQPChannel::qos')
            ->once()
            ->andThrow(new AMQPChannelException('Bang!'));

        $this->expectException(AMQPChannelException::class);
        $this->expectExceptionMessage('Bang!');

        $this->channel->basicQos(128, 10, false, AMQPChannelException::class, 'AMQPChannel::qos');
    }

    /**
     * @dataProvider booleanDataProvider
     */
    public function testBasicRecoverPassesRequeueThroughToAmqplib(bool $requeue): void
    {
        $this->amqplibChannel->expects()
            ->basic_recover($requeue)
            ->once();

        $this->channel->basicRecover($requeue, AMQPChannelException::class, 'AMQPChannel::basicRecover');
    }

    public function testBasicRecoverPassesExceptionToHandlerOnAmqplibFailure(): void
    {
        $exception = new AMQPLogicException('Bang!');
        $this->amqplibChannel->allows('basic_recover')
            ->andThrow($exception);

        $this->exceptionHandler->expects()
            ->handleException($exception, AMQPChannelException::class, 'AMQPChannel::basicRecover')
            ->once()
            ->andThrow(new AMQPChannelException('Bang!'));

        $this->expectException(AMQPChannelException::class);
        $this->expectExceptionMessage('Bang!');

        $this->channel->basicRecover(false, AMQPChannelException::class, 'AMQPChannel::basicRecover');
    }

    /**
     * @dataProvider booleanDataProvider
     */
    public function testBasicRejectPassesRequeueThroughToAmqplib(bool $requeue): void
    {
        $this->amqplibChannel->expects()
            ->basic_reject(4321, $requeue)
            ->once();

        $this->channel->basicReject(4321, $requeue, AMQPQueueException::class, 'AMQPQueue::reject');
    }

    public function testBasicRejectPassesExceptionToHandlerOnAmqplibFailure(): void
    {
        $exception = new AMQPLogicException('Bang!');
        $this->amqplibChannel->allows('basic_reject')
            ->andThrow($exception);

        $this->exceptionHandler->expects()
            ->handleException($exception, AMQPQueueException::class, 'AMQPQueue::reject')
            ->once()
            ->andThrow(new AMQPQueueException('Bang!'));

        $this->expectException(AMQPQueueException::class);
        $this->expectExceptionMessage('Bang!');

        $this->channel->basicReject(4321, false, AMQPQueueException::class, 'AMQPQueue::reject');
    }

    public function testBindExchangeCallsAmqplibWithCorrectArgs(): void
    {
        $arguments = ['x-first' => 'one', 'x-second' => 'two'];

        $this->amqplibChannel->expects()
            ->exchange_bind('my_dest', 'my_src', 'my_key', false, Mockery::type(AmqplibTable::class))
            ->once()
            ->andReturnUsing(function ($_1, $_2, $_3, $_4, AmqplibTable $table) use ($arguments) {
                static::assertEquals($arguments, $table->getNativeData());
            });

        $this->channel->bindExchange(
            'my_dest',
            'my_src',
            'my_key',
            false,
            $arguments,
            AMQPExchangeException::class,
            'AMQPExchange::bind'
        );
    }

    /**
     * @dataProvider booleanDataProvider
     */
    public function testBindExchangePassesNoWaitThroughToAmqplib(bool $noWait): void
    {
        $this->amqplibChannel->expects()
            ->exchange_bind('my_dest', 'my_src', 'my_key', $noWait, Mockery::type(AmqplibTable::class))
            ->once();

        $this->channel->bindExchange(
            'my_dest',
            'my_src',
            'my_key',
            $noWait,
            [],
            AMQPExchangeException::class,
            'AMQPExchange::bind'
        );
    }

    public function testBindExchangePassesExceptionToHandlerOnAmqplibFailure(): void
    {
        $exception = new AMQPLogicException('Bang!');
        $this->amqplibChannel->allows('exchange_bind')
            ->andThrow($exception);

        $this->exceptionHandler->expects()
            ->handleException($exception, AMQPExchangeException::class, 'AMQPExchange::bind')
            ->once()
            ->andThrow(new AMQPExchangeException('Bang!'));

        $this->expectException(AMQPExchangeException::class);
        $this->expectExceptionMessage('Bang!');

        $this->channel->bindExchange(
            'my_dest',
            'my_src',
            'my_key',
            false,
            [],
            AMQPExchangeException::class,
            'AMQPExchange::bind'
        );
    }

    public function testBindQueueCallsAmqplibWithCorrectArgs(): void
    {
        $arguments = ['x-first' => 'one', 'x-second' => 'two'];

        $this->amqplibChannel->expects()
            ->queue_bind('my_queue', 'my_exchange', 'my_key', false, Mockery::type(AmqplibTable::class))
            ->once()
            ->andReturnUsing(function ($_1, $_2, $_3, $_4, AmqplibTable $table) use ($arguments) {
                static::assertEquals($arguments, $table->getNativeData());
            });

        $this->channel->bindQueue(
            'my_queue',
            'my_exchange',
            'my_key',
            $arguments,
            AMQPQueueException::class,
            'AMQPQueue::bind'
        );
    }

    public function testBindQueuePassesExceptionToHandlerOnAmqplibFailure(): void
    {
        $exception = new AMQPLogicException('Bang!');
        $this->amqplibChannel->allows('queue_bind')
            ->andThrow($exception);

        $this->exceptionHandler->expects()
            ->handleException($exception, AMQPQueueException::class, 'AMQPQueue::bind')
            ->once()
            ->andThrow(new AMQPQueueException('Bang!'));

        $this->expectException(AMQPQueueException::class);
        $this->expectExceptionMessage('Bang!');

        $this->channel->bindQueue(
            'my_queue',
            'my_exchange',
            'my_key',
            [],
            AMQPQueueException::class,
            'AMQPQueue::bind'
        );
    }

    public function testCloseQuietlyCallsCloseIfDisconnectedThenCloseWhenOpen(): void
    {
        $this->amqplibChannel->allows('is_open')
            ->andReturnTrue();

        $this->amqplibChannel->expects('closeIfDisconnected')
            ->once();
        $this->amqplibChannel->expects('close')
            ->once();

        $this->channel->closeQuietly();
    }

    public function testCloseQuietlyCallsCloseIfDisconnectedButNotCloseWhenNotOpen(): void
    {
        $this->amqplibChannel->allows('is_open')
            ->andReturnFalse();

        $this->amqplibChannel->expects('closeIfDisconnected')
            ->once();
        $this->amqplibChannel->expects('close')
            ->never();

        $this->channel->closeQuietly();
    }

    public function testCloseQuietlySwallowsAmqplibExceptions(): void
    {
        $this->amqplibChannel->allows('closeIfDisconnected')
            ->andThrow(new AMQPLogicException('Bang!'));

        $this->expectNotToPerformAssertions();

        $this->channel->closeQuietly();
    }

    public function testCommitTransactionCallsAmqplib(): void
    {
        $this->amqplibChannel->expects('tx_commit')
            ->once();

        $this->channel->commitTransaction(AMQPChannelException::class, 'AMQPChannel::commitTransaction');
    }

    public function testCommitTransactionPassesExceptionToHandlerOnAmqplibFailure(): void
    {
        $exception = new AMQPLogicException('Bang!');
        $this->amqplibChannel->allows('tx_commit')
            ->andThrow($exception);

        $this->exceptionHandler->expects()
            ->handleException($exception, AMQPChannelException::class, 'AMQPChannel::commitTransaction')
            ->once()
            ->andThrow(new AMQPChannelException('Bang!'));

        $this->expectException(AMQPChannelException::class);
        $this->expectExceptionMessage('Bang!');

        $this->channel->commitTransaction(AMQPChannelException::class, 'AMQPChannel::commitTransaction');
    }

    public function testDeclareExchangeCallsAmqplibWithCorrectArgs(): void
    {
        $arguments = ['x-first' => 'one', 'x-second' => 'two'];

        $this->amqplibChannel->expects()
            ->exchange_declare('my_exchange', 'direct', false, true, false, false, false, Mockery::type(AmqplibTable::class))
            ->once()
            ->andReturnUsing(function ($_1, $_2, $_3, $_4, $_5, $_6, $_7, AmqplibTable $table) use ($arguments) {
                static::assertEquals($arguments, $table->getNativeData());
            });

        $this->channel->declareExchange(
            'my_exchange',
            'direct',
            false,
            true,
            false,
            false,
            false,
            $arguments,
            AMQPExchangeException::class,
            'AMQPExchange::declareExchange'
        );
    }

    public function testDeclareExchangePassesExceptionToHandlerOnAmqplibFailure(): void
    {
        $exception = new AMQPLogicException('Bang!');
        $this->amqplibChannel->allows('exchange_declare')
            ->andThrow($exception);

        $this->exceptionHandler->expects()
            ->handleException($exception, AMQPExchangeException::class, 'AMQPExchange::declareExchange')
            ->once()
            ->andThrow(new AMQPExchangeException('Bang!'));

        $this->expectException(AMQPExchangeException::class);
        $this->expectExceptionMessage('Bang!');

        $this->channel->declareExchange(
            'my_exchange',
            'direct',
            false,
            false,
            false,
            false,
            false,
            [],
            AMQPExchangeException::class,
            'AMQPExchange::declareExchange'
        );
    }

    public function testDeclareQueueCallsAmqplibWithCorrectArgs(): void
    {
        $arguments = ['x-first' => 'one', 'x-second' => 'two'];

        $this->amqplibChannel->expects()
            ->queue_declare('my_queue', false, true, false, false, false, Mockery::type(AmqplibTable::class))
            ->once()
            ->andReturnUsing(function ($_1, $_2, $_3, $_4, $_5, $_6, AmqplibTable $table) use ($arguments) {
                static::assertEquals($arguments, $table->getNativeData());

                return ['my_queue', 21, 7];
            });

        $this->channel->declareQueue(
            'my_queue',
            false,
            true,
            false,
            false,
            false,
            $arguments,
            AMQPQueueException::class,
            'AMQPQueue::declareQueue'
        );
    }

    public function testDeclareQueueReturnsNameAndCount(): void
    {
        $this->amqplibChannel->allows('queue_declare')
            ->andReturn(['my_auto_queue', 42, 7]);

        $result = $this->channel->declareQueue(
            '',
            false,
            false,
            false,
            false,
            false,
            [],
            AMQPQueueException::class,
            'AMQPQueue::declareQueue'
        );

        static::assertSame(['name' => 'my_auto_queue', 'count' => 42], $result);
    }

    public function testDeclareQueueThrowsWhenAmqplibReturnsNonArray(): void
    {
        $this->amqplibChannel->allows('queue_declare')
            ->andReturnNull();

        $this->expectException(AMQPQueueException::class);
        $this->expectExceptionMessage('AMQPQueue::declareQueue(): Amqplib result was not an array');

        $this->channel->declareQueue(
            'my_queue',
            false,
            false,
            false,
            false,
            false,
            [],
            AMQPQueueException::class,
            'AMQPQueue::declareQueue'
        );
    }

    public function testDeclareQueueThrowsWhenAmqplibReturnsArrayWithTooFewElements(): void
    {
        $this->amqplibChannel->allows('queue_declare')
            ->andReturn(['my_queue']);

        $this->expectException(AMQPQueueException::class);
        $this->expectExceptionMessage('AMQPQueue::declareQueue(): Amqplib result should contain message count at [1]');

        $this->channel->declareQueue(
            'my_queue',
            false,
            false,
            false,
            false,
            false,
            [],
            AMQPQueueException::class,
            'AMQPQueue::declareQueue'
        );
    }

    public function testDeclareQueuePassesExceptionToHandlerOnAmqplibFailure(): void
    {
        $exception = new AMQPLogicException('Bang!');
        $this->amqplibChannel->allows('queue_declare')
            ->andThrow($exception);

        $this->exceptionHandler->expects()
            ->handleException($exception, AMQPQueueException::class, 'AMQPQueue::declareQueue')
            ->once()
            ->andThrow(new AMQPQueueException('Bang!'));

        $this->expectException(AMQPQueueException::class);
        $this->expectExceptionMessage('Bang!');

        $this->channel->declareQueue(
            'my_queue',
            false,
            false,
            false,
            false,
            false,
            [],
            AMQPQueueException::class,
            'AMQPQueue::declareQueue'
        );
    }

    public function testDeleteExchangeCallsAmqplibWithCorrectArgs(): void
    {
        $this->amqplibChannel->expects()
            ->exchange_delete('my_exchange', true, false)
            ->once();

        $this->channel->deleteExchange(
            'my_exchange',
            true,
            false,
            AMQPExchangeException::class,
            'AMQPExchange::delete'
        );
    }

    public function testDeleteExchangePassesExceptionToHandlerOnAmqplibFailure(): void
    {
        $exception = new AMQPLogicException('Bang!');
        $this->amqplibChannel->allows('exchange_delete')
            ->andThrow($exception);

        $this->exceptionHandler->expects()
            ->handleException($exception, AMQPExchangeException::class, 'AMQPExchange::delete')
            ->once()
            ->andThrow(new AMQPExchangeException('Bang!'));

        $this->expectException(AMQPExchangeException::class);
        $this->expectExceptionMessage('Bang!');

        $this->channel->deleteExchange(
            'my_exchange',
            false,
            false,
            AMQPExchangeException::class,
            'AMQPExchange::delete'
        );
    }

    public function testDeleteQueueCallsAmqplibWithCorrectArgs(): void
    {
        $this->amqplibChannel->expects()
            ->queue_delete('my_queue', true, true, false)
            ->once()
            ->andReturn(5);

        $this->channel->deleteQueue(
            'my_queue',
            true,
            true,
            false,
            AMQPQueueException::class,
            'AMQPQueue::delete'
        );
    }

    public function testDeleteQueueReturnsDeletedMessageCount(): void
    {
        $this->amqplibChannel->allows('queue_delete')
            ->andReturn(7);

        $result = $this->channel->deleteQueue(
            'my_queue',
            false,
            false,
            false,
            AMQPQueueException::class,
            'AMQPQueue::delete'
        );

        static::assertSame(7, $result);
    }

    public function testDeleteQueuePassesExceptionToHandlerOnAmqplibFailure(): void
    {
        $exception = new AMQPLogicException('Bang!');
        $this->amqplibChannel->allows('queue_delete')
            ->andThrow($exception);

        $this->exceptionHandler->expects()
            ->handleException($exception, AMQPQueueException::class, 'AMQPQueue::delete')
            ->once()
            ->andThrow(new AMQPQueueException('Bang!'));

        $this->expectException(AMQPQueueException::class);
        $this->expectExceptionMessage('Bang!');

        $this->channel->deleteQueue(
            'my_queue',
            false,
            false,
            false,
            AMQPQueueException::class,
            'AMQPQueue::delete'
        );
    }

    public function testGetChannelIdReturnsIdFromAmqplib(): void
    {
        $this->amqplibChannel->allows('getChannelId')
            ->andReturn(42);

        static::assertSame(42, $this->channel->getChannelId());
    }

    public function testGetChannelIdReturnsNullWhenAmqplibReturnsNull(): void
    {
        $this->amqplibChannel->allows('getChannelId')
            ->andReturnNull();

        static::assertNull($this->channel->getChannelId());
    }

    public function testHasConnectionReturnsTrueWhenAmqplibHasConnection(): void
    {
        $this->amqplibChannel->allows('getConnection')
            ->andReturn(mock(AmqplibConnection::class));

        static::assertTrue($this->channel->hasConnection());
    }

    public function testHasConnectionReturnsFalseWhenAmqplibHasNoConnection(): void
    {
        $this->amqplibChannel->allows('getConnection')
            ->andReturnNull();

        static::assertFalse($this->channel->hasConnection());
    }

    /**
     * @dataProvider booleanDataProvider
     */
    public function testIsConnectedDelegatesToTransport(bool $connected): void
    {
        $this->transport->allows('isConnected')
            ->andReturn($connected);

        static::assertSame($connected, $this->channel->isConnected());
    }

    /**
     * @dataProvider booleanDataProvider
     */
    public function testIsOpenDelegatesToAmqplibChannel(bool $open): void
    {
        $this->amqplibChannel->allows('is_open')
            ->andReturn($open);

        static::assertSame($open, $this->channel->isOpen());
    }

    public function testPurgeQueueCallsAmqplibWithCorrectArgs(): void
    {
        $this->amqplibChannel->expects()
            ->queue_purge('my_queue', false)
            ->once();

        $this->channel->purgeQueue('my_queue', false, AMQPQueueException::class, 'AMQPQueue::purge');
    }

    public function testPurgeQueuePassesExceptionToHandlerOnAmqplibFailure(): void
    {
        $exception = new AMQPLogicException('Bang!');
        $this->amqplibChannel->allows('queue_purge')
            ->andThrow($exception);

        $this->exceptionHandler->expects()
            ->handleException($exception, AMQPQueueException::class, 'AMQPQueue::purge')
            ->once()
            ->andThrow(new AMQPQueueException('Bang!'));

        $this->expectException(AMQPQueueException::class);
        $this->expectExceptionMessage('Bang!');

        $this->channel->purgeQueue('my_queue', false, AMQPQueueException::class, 'AMQPQueue::purge');
    }

    public function testRollbackTransactionCallsAmqplib(): void
    {
        $this->amqplibChannel->expects('tx_rollback')
            ->once();

        $this->channel->rollbackTransaction(AMQPChannelException::class, 'AMQPChannel::rollbackTransaction');
    }

    public function testRollbackTransactionPassesExceptionToHandlerOnAmqplibFailure(): void
    {
        $exception = new AMQPLogicException('Bang!');
        $this->amqplibChannel->allows('tx_rollback')
            ->andThrow($exception);

        $this->exceptionHandler->expects()
            ->handleException($exception, AMQPChannelException::class, 'AMQPChannel::rollbackTransaction')
            ->once()
            ->andThrow(new AMQPChannelException('Bang!'));

        $this->expectException(AMQPChannelException::class);
        $this->expectExceptionMessage('Bang!');

        $this->channel->rollbackTransaction(AMQPChannelException::class, 'AMQPChannel::rollbackTransaction');
    }

    public function testStartTransactionCallsAmqplib(): void
    {
        $this->amqplibChannel->expects('tx_select')
            ->once();

        $this->channel->startTransaction(AMQPChannelException::class, 'AMQPChannel::startTransaction');
    }

    public function testStartTransactionPassesExceptionToHandlerOnAmqplibFailure(): void
    {
        $exception = new AMQPLogicException('Bang!');
        $this->amqplibChannel->allows('tx_select')
            ->andThrow($exception);

        $this->exceptionHandler->expects()
            ->handleException($exception, AMQPChannelException::class, 'AMQPChannel::startTransaction')
            ->once()
            ->andThrow(new AMQPChannelException('Bang!'));

        $this->expectException(AMQPChannelException::class);
        $this->expectExceptionMessage('Bang!');

        $this->channel->startTransaction(AMQPChannelException::class, 'AMQPChannel::startTransaction');
    }

    public function testUnbindExchangeCallsAmqplibWithCorrectArgs(): void
    {
        $arguments = ['x-first' => 'one', 'x-second' => 'two'];

        $this->amqplibChannel->expects()
            ->exchange_unbind('my_dest', 'my_src', 'my_key', false, Mockery::type(AmqplibTable::class))
            ->once()
            ->andReturnUsing(function ($_1, $_2, $_3, $_4, AmqplibTable $table) use ($arguments) {
                static::assertEquals($arguments, $table->getNativeData());

                return ['my_queue', 21, 7];
            });

        $this->channel->unbindExchange(
            'my_dest',
            'my_src',
            'my_key',
            false,
            $arguments,
            AMQPExchangeException::class,
            'AMQPExchange::unbind'
        );
    }

    /**
     * @dataProvider booleanDataProvider
     */
    public function testUnbindExchangePassesNoWaitThroughToAmqplib(bool $noWait): void
    {
        $this->amqplibChannel->expects()
            ->exchange_unbind('my_dest', 'my_src', 'my_key', $noWait, Mockery::type(AmqplibTable::class))
            ->once();

        $this->channel->unbindExchange(
            'my_dest',
            'my_src',
            'my_key',
            $noWait,
            [],
            AMQPExchangeException::class,
            'AMQPExchange::unbind'
        );
    }

    public function testUnbindExchangePassesExceptionToHandlerOnAmqplibFailure(): void
    {
        $exception = new AMQPLogicException('Bang!');
        $this->amqplibChannel->allows('exchange_unbind')
            ->andThrow($exception);

        $this->exceptionHandler->expects()
            ->handleException($exception, AMQPExchangeException::class, 'AMQPExchange::unbind')
            ->once()
            ->andThrow(new AMQPExchangeException('Bang!'));

        $this->expectException(AMQPExchangeException::class);
        $this->expectExceptionMessage('Bang!');

        $this->channel->unbindExchange(
            'my_dest',
            'my_src',
            'my_key',
            false,
            [],
            AMQPExchangeException::class,
            'AMQPExchange::unbind'
        );
    }

    public function testWaitCallsAmqplibWithTimeout(): void
    {
        $this->amqplibChannel->expects()
            ->wait(null, false, 60)
            ->once();

        $this->channel->wait(60, AMQPQueueException::class, 'AMQPQueue::consume');
    }

    public function testWaitPassesIsConsumptionTrueToExceptionHandlerOnAmqplibFailure(): void
    {
        $exception = new AMQPLogicException('Bang!');
        $this->amqplibChannel->allows()
            ->wait(null, false, 60)
            ->andThrow($exception);

        $this->exceptionHandler->expects()
            ->handleException($exception, AMQPQueueException::class, 'AMQPQueue::consume', true)
            ->once()
            ->andThrow(new AMQPQueueException('Bang!'));

        $this->expectException(AMQPQueueException::class);
        $this->expectExceptionMessage('Bang!');

        $this->channel->wait(60, AMQPQueueException::class, 'AMQPQueue::consume');
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function booleanDataProvider(): array
    {
        return [
            'true' => [true],
            'false' => [false],
        ];
    }
}
