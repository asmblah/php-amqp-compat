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

namespace Asmblah\PhpAmqpCompat\Tests\Integrated\Amqp;

use AMQPChannel;
use AMQPConnection;
use AMQPEnvelope;
use AMQPExchange;
use AMQPQueue;
use AMQPQueueException;
use Asmblah\PhpAmqpCompat\AmqpManager;
use Asmblah\PhpAmqpCompat\Bridge\AmqpBridge;
use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridge;
use Asmblah\PhpAmqpCompat\Configuration\ConfigurationInterface;
use Asmblah\PhpAmqpCompat\Connection\Config\ConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Connection\Config\TimeoutDeprecationUsageEnum;
use Asmblah\PhpAmqpCompat\Driver\Common\Channel\ChannelInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Logger\LoggerInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Transport\TransportInterface;
use Asmblah\PhpAmqpCompat\Error\ErrorReporterInterface;
use Asmblah\PhpAmqpCompat\Exception\StopConsumptionException;
use Asmblah\PhpAmqpCompat\Integration\AmqpIntegrationInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Closure;
use Mockery;
use Mockery\MockInterface;

/**
 * Class PublishThenConsumeTest.
 *
 * Drives the AMQP* API without actually talking to a real AMQP broker server instance.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class PublishThenConsumeTest extends AbstractTestCase
{
    private AMQPChannel $amqpChannel;
    private AMQPConnection $amqpConnection;
    private AmqpConnectionBridge $amqpConnectionBridge;
    private AMQPExchange $amqpExchange;
    private MockInterface&AmqpIntegrationInterface $amqpIntegration;
    private AMQPQueue $amqpQueue;
    private MockInterface&ChannelInterface $channel;
    private MockInterface&ConnectionConfigInterface $connectionConfig;
    private MockInterface&ErrorReporterInterface $errorReporter;
    private MockInterface&LoggerInterface $logger;
    private MockInterface&TransportInterface $transport;

    public function setUp(): void
    {
        AmqpBridge::initialise();

        $this->connectionConfig = mock(ConnectionConfigInterface::class, [
            'getConnectionTimeout' => 0,
            'getDeprecatedTimeoutCredentialUsage' => TimeoutDeprecationUsageEnum::NOT_USED,
            'getDeprecatedTimeoutIniSettingUsage' => TimeoutDeprecationUsageEnum::NOT_USED,
            'getGlobalPrefetchCount' => 10,
            'getGlobalPrefetchSize' => 512,
            'getPrefetchCount' => 4,
            'getPrefetchSize' => 128,
            'getReadTimeout' => 60,
            'toLoggableArray' => ['my' => 'loggable connection config'],
        ]);
        $this->logger = mock(LoggerInterface::class, [
            'debug' => null,
        ]);
        $this->errorReporter = mock(ErrorReporterInterface::class);
        $this->amqpIntegration = mock(AmqpIntegrationInterface::class, [
            'createConnectionConfig' => $this->connectionConfig,
            'getConfiguration' => mock(ConfigurationInterface::class),
            'getErrorReporter' => $this->errorReporter,
            'getLogger' => $this->logger,
        ]);
        $this->channel = mock(ChannelInterface::class, [
            'basicPublish' => null,
            'basicQos' => null,
            'bindQueue' => null,
            'closeQuietly' => null,
            'declareExchange' => null,
            'declareQueue' => ['name' => 'my_queue', 'count' => 21],
            'hasConnection' => true,
            'isConnected' => true,
            'isOpen' => true,
        ]);
        $this->transport = mock(TransportInterface::class, [
            'checkHeartbeat' => null,
            'isConnected' => true,
            'openChannel' => $this->channel,
        ]);

        $this->amqpConnectionBridge = new AmqpConnectionBridge(
            $this->transport,
            $this->connectionConfig,
            $this->errorReporter,
            $this->logger
        );

        AmqpManager::setAmqpIntegration($this->amqpIntegration);

        $this->amqpConnection = new AMQPConnection();
        AmqpBridge::bridgeConnection($this->amqpConnection, $this->amqpConnectionBridge);
        $this->amqpChannel = new AMQPChannel($this->amqpConnection);

        $this->amqpExchange = new AMQPExchange($this->amqpChannel);
        $this->amqpExchange->setName('my_exchange');
        $this->amqpExchange->setType(AMQP_EX_TYPE_DIRECT);
        $this->amqpExchange->declareExchange();

        $this->amqpQueue = new AMQPQueue($this->amqpChannel);
        $this->amqpQueue->setName('my_queue');
        $this->amqpQueue->bind('my_exchange', 'my_routing_key');
        $this->amqpQueue->declareQueue();
    }

    public function testPublishThenConsumeWorksAsExpected(): void
    {
        $amqpEnvelope = new AMQPEnvelope(
            body: 'my message body',
            consumerTag: 'my-consumer-tag'
        );

        $consumerCallback = null;

        $this->channel->expects()
            ->basicConsume('my_queue', '', false, false, false, Mockery::type(Closure::class), \AMQPQueueException::class, 'AMQPQueue::consume')
            ->andReturnUsing(function (
                string $queue,
                string $tag,
                bool $noLocal,
                bool $autoAck,
                bool $exclusive,
                callable $callback,
                string $exceptionClass,
                string $methodName
            ) use (&$consumerCallback) {
                $consumerCallback = $callback;

                return 'my-consumer-tag';
            });
        $this->channel->expects()
            ->wait(60, AMQPQueueException::class, 'AMQPQueue::consume')
            ->andReturnUsing(function () use ($amqpEnvelope, &$consumerCallback) {
                $consumerCallback($amqpEnvelope);

                throw new StopConsumptionException();
            });

        $this->amqpExchange->publish('my message', 'my_routing_key');

        /** @var AMQPEnvelope[] $envelopes */
        $envelopes = [];

        $this->amqpQueue->consume(
            function (AMQPEnvelope $amqpEnvelope) use (&$envelopes) {
                $envelopes[] = $amqpEnvelope;
            }
        );

        static::assertCount(1, $envelopes);
        static::assertSame('my message body', $envelopes[0]->getBody());
    }
}
