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
use Asmblah\PhpAmqpCompat\AmqpManager;
use Asmblah\PhpAmqpCompat\Bridge\AmqpBridge;
use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridge;
use Asmblah\PhpAmqpCompat\Connection\Config\ConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Connection\Config\TimeoutDeprecationUsageEnum;
use Asmblah\PhpAmqpCompat\Error\ErrorReporterInterface;
use Asmblah\PhpAmqpCompat\Exception\StopConsumptionException;
use Asmblah\PhpAmqpCompat\Integration\AmqpIntegrationInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Mockery;
use Mockery\MockInterface;
use PhpAmqpLib\Channel\AMQPChannel as AmqplibChannel;
use PhpAmqpLib\Connection\AbstractConnection as AmqplibConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

/**
 * Class PublishThenConsumeTest.
 *
 * Drives the AMQP* API without actually talking to a real AMQP broker server instance.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class PublishThenConsumeTest extends AbstractTestCase
{
    private AMQPChannel|null $amqpChannel;
    private AMQPConnection|null $amqpConnection;
    private AmqpConnectionBridge|null $amqpConnectionBridge;
    private AMQPExchange|null $amqpExchange;
    /**
     * @var (MockInterface&AmqpIntegrationInterface)|null
     */
    private $amqpIntegration;
    private AMQPQueue|null $amqpQueue;
    private AmqplibChannel|null $amqplibChannel;
    private AmqplibConnection|null $amqplibConnection;
    /**
     * @var (MockInterface&ConnectionConfigInterface)|null
     */
    private $connectionConfig;
    /**
     * @var (MockInterface&ErrorReporterInterface)|null
     */
    private $errorReporter;
    /**
     * @var (MockInterface&LoggerInterface)|null
     */
    private $logger;

    public function setUp(): void
    {
        AmqpBridge::initialise();

        $this->connectionConfig = mock(ConnectionConfigInterface::class, [
            'getConnectionTimeout' => 0,
            'getDeprecatedTimeoutCredentialUsage' => TimeoutDeprecationUsageEnum::NOT_USED,
            'getDeprecatedTimeoutIniSettingUsage' => TimeoutDeprecationUsageEnum::NOT_USED,
            'toLoggableArray' => ['my' => 'loggable connection config'],
        ]);
        $this->logger = mock(LoggerInterface::class, [
            'debug' => null,
        ]);
        $this->errorReporter = mock(ErrorReporterInterface::class);
        $this->amqpIntegration = mock(AmqpIntegrationInterface::class, [
            'createConnectionConfig' => $this->connectionConfig,
            'getErrorReporter' => $this->errorReporter,
            'getLogger' => $this->logger,
        ]);
        $this->amqplibChannel = mock(AmqplibChannel::class, [
            'basic_consume' => 'my-consumer-tag',
            'basic_publish' => null,
            'close' => null,
            'exchange_declare' => null,
            'is_open' => true,
            'queue_bind' => null,
        ]);
        $this->amqplibConnection = mock(AmqplibConnection::class, [
            'channel' => $this->amqplibChannel,
            'isConnected' => true,
        ]);

        $this->amqplibChannel->allows()
            ->getConnection()
            ->andReturn($this->amqplibConnection);

        $this->amqpConnectionBridge = new AmqpConnectionBridge($this->amqplibConnection);

        $this->amqpIntegration->allows()
            ->connect($this->connectionConfig)
            ->andReturn($this->amqpConnectionBridge);

        AmqpManager::setAmqpIntegration($this->amqpIntegration);

        $this->amqpConnection = new AMQPConnection();
        AmqpBridge::bridgeConnection($this->amqpConnection, $this->amqpConnectionBridge);
        $this->amqpChannel = new AMQPChannel($this->amqpConnection);

        $this->amqpExchange = new AMQPExchange($this->amqpChannel);
        $this->amqpExchange->setName('my_exchange');
        $this->amqpExchange->declareExchange();

        $this->amqpQueue = new AMQPQueue($this->amqpChannel);
        $this->amqpQueue->setName('my_queue');
        $this->amqpQueue->bind('my_exchange', 'my_routing_key');
//        $this->amqpQueue->declareQueue();
    }

    public function testPublishThenConsumeWorksAsExpected(): void
    {
        $amqplibMessage = new AMQPMessage('my message body');
        $amqplibMessage->setConsumerTag('my-consumer-tag');
        $amqplibMessage->setDeliveryInfo(1234, false, 'my-exchange', 'my-routing-key');

        $consumerCallback = null;

        $this->amqplibChannel->expects()
            ->basic_consume(Mockery::andAnyOthers())
            ->andReturnUsing(function (
                $queue,
                $tag,
                $noLocal,
                $noAck,
                $exclusive,
                $noWait,
                callable $callback
            ) use (&$consumerCallback) {
                $consumerCallback = $callback;

                return 'my-consumer-tag';
            });
        $this->amqplibChannel->expects()
            ->wait()
            ->andReturnUsing(function () use ($amqplibMessage, &$consumerCallback) {
                $consumerCallback($amqplibMessage);

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
