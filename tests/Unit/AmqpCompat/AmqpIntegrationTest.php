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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\AmqpCompat;

use Asmblah\PhpAmqpCompat\Bridge\Channel\EnvelopeTransformerInterface;
use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Configuration\ConfigurationInterface;
use Asmblah\PhpAmqpCompat\Connection\Config\ConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Connection\Config\DefaultConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Connection\Config\TimeoutDeprecationUsageEnum;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transformer\MessageTransformerInterface;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transport\Transport;
use Asmblah\PhpAmqpCompat\Driver\Common\Transport\TransportConnectorInterface;
use Asmblah\PhpAmqpCompat\Error\ErrorReporterInterface;
use Asmblah\PhpAmqpCompat\Heartbeat\HeartbeatSenderInterface;
use Asmblah\PhpAmqpCompat\Integration\AmqpIntegration;
use Asmblah\PhpAmqpCompat\Logger\LoggerInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Mockery;
use Mockery\MockInterface;
use PhpAmqpLib\Connection\AbstractConnection as AmqplibConnection;

/**
 * Class AmqpIntegrationTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqpIntegrationTest extends AbstractTestCase
{
    private AmqpIntegration $amqpIntegration;
    private MockInterface&AmqplibConnection $amqplibConnection;
    private MockInterface&ConnectionConfigInterface $connectionConfig;
    private MockInterface&ConfigurationInterface $configuration;
    private MockInterface&DefaultConnectionConfigInterface $defaultConnectionConfig;
    private MockInterface&EnvelopeTransformerInterface $envelopeTransformer;
    private MockInterface&ErrorReporterInterface $errorReporter;
    private MockInterface&HeartbeatSenderInterface $heartbeatSender;
    private MockInterface&LoggerInterface $logger;
    private MockInterface&MessageTransformerInterface $messageTransformer;
    private MockInterface&Transport $transport;
    private MockInterface&TransportConnectorInterface $transportConnector;

    public function setUp(): void
    {
        $this->amqplibConnection = mock(AmqplibConnection::class);
        $this->defaultConnectionConfig = mock(DefaultConnectionConfigInterface::class, [
            'getConnectionTimeout' => 123.0,
            'getHeartbeatInterval' => 234,
            'getHost' => 'my.default.host',
            'getMaxChannels' => 12,
            'getMaxFrameSize' => 34,
            'getPassword' => 'mydefaultpass',
            'getPort' => 345,
            'getReadTimeout' => 456.0,
            'getRpcTimeout' => 567.0,
            'getUser' => 'mydefaultuser',
            'getVirtualHost' => '/my/default/vhost',
            'getWriteTimeout' => 678.0,
        ]);
        $this->envelopeTransformer = mock(EnvelopeTransformerInterface::class);
        $this->logger = mock(LoggerInterface::class);
        $this->messageTransformer = mock(MessageTransformerInterface::class);
        $this->errorReporter = mock(ErrorReporterInterface::class);
        $this->configuration = mock(ConfigurationInterface::class, [
            'getErrorReporter' => $this->errorReporter,
            'getLogger' => $this->logger,
        ]);
        $this->connectionConfig = mock(ConnectionConfigInterface::class);
        $this->transport = mock(Transport::class, [
            'getAmqplibConnection' => $this->amqplibConnection,
        ]);
        $this->transportConnector = mock(TransportConnectorInterface::class, [
            'connect' => $this->transport,
        ]);
        $this->heartbeatSender = mock(HeartbeatSenderInterface::class, [
            'register' => null,
        ]);

        $this->amqpIntegration = new AmqpIntegration(
            $this->transportConnector,
            $this->heartbeatSender,
            $this->configuration,
            $this->defaultConnectionConfig,
            $this->envelopeTransformer,
            $this->messageTransformer
        );
    }

    public function testConnectConnectsViaTheTransportConnector(): void
    {
        $this->transportConnector->expects()
            ->connect($this->connectionConfig)
            ->once()
            ->andReturn($this->transport);

        $this->amqpIntegration->connect($this->connectionConfig);
    }

    public function testConnectReturnsAConnectionBridgeUsingTheAmqplibConnection(): void
    {
        $connectionBridge = $this->amqpIntegration->connect($this->connectionConfig);

        static::assertSame($this->amqplibConnection, $connectionBridge->getAmqplibConnection());
    }

    public function testConnectReturnsAConnectionBridgeUsingTheEnvelopeTransformer(): void
    {
        $connectionBridge = $this->amqpIntegration->connect($this->connectionConfig);

        static::assertSame($this->envelopeTransformer, $connectionBridge->getEnvelopeTransformer());
    }

    public function testConnectRegistersTheCreatedBridgeWithTheHeartbeatSender(): void
    {
        $this->heartbeatSender->expects()
            ->register(Mockery::type(AmqpConnectionBridgeInterface::class))
            ->once();

        $this->amqpIntegration->connect($this->connectionConfig);
    }

    public function testConnectReturnsAConnectionBridgeUsingTheMessageTransformer(): void
    {
        $connectionBridge = $this->amqpIntegration->connect($this->connectionConfig);

        static::assertSame($this->messageTransformer, $connectionBridge->getMessageTransformer());
    }

    public function testCreateConnectionConfigUsesCorrectDefaults(): void
    {
        $config = $this->amqpIntegration->createConnectionConfig([]);

        static::assertSame(123.0, $config->getConnectionTimeout());
        static::assertSame(234, $config->getHeartbeatInterval());
        static::assertSame('my.default.host', $config->getHost());
        static::assertSame(12, $config->getMaxChannels());
        static::assertSame(34, $config->getMaxFrameSize());
        static::assertSame('mydefaultpass', $config->getPassword());
        static::assertSame(345, $config->getPort());
        static::assertSame(456.0, $config->getReadTimeout());
        static::assertSame(567.0, $config->getRpcTimeout());
        static::assertSame('mydefaultuser', $config->getUser());
        static::assertSame('/my/default/vhost', $config->getVirtualHost());
        static::assertSame(678.0, $config->getWriteTimeout());
    }

    public function testCreateConnectionConfigUsesGivenSettings(): void
    {
        $config = $this->amqpIntegration->createConnectionConfig([
            'channel_max' => 567,
            'connect_timeout' => 12.34,
            'frame_max' => 678,
            'heartbeat' => 123,
            'host' => 'myhost',
            'login' => 'myuser',
            'password' => 'my password',
            'port' => 1234,
            'read_timeout' => 56.78,
            'rpc_timeout' => 34.56,
            'vhost' => '/my/vhost',
            'write_timeout' => 90.12,
        ]);

        static::assertSame(12.34, $config->getConnectionTimeout());
        static::assertSame(123, $config->getHeartbeatInterval());
        static::assertSame('myhost', $config->getHost());
        static::assertSame(567, $config->getMaxChannels());
        static::assertSame(678, $config->getMaxFrameSize());
        static::assertSame('my password', $config->getPassword());
        static::assertSame(1234, $config->getPort());
        static::assertSame(56.78, $config->getReadTimeout());
        static::assertSame(34.56, $config->getRpcTimeout());
        static::assertSame('myuser', $config->getUser());
        static::assertSame('/my/vhost', $config->getVirtualHost());
        static::assertSame(90.12, $config->getWriteTimeout());
    }

    public function testCreateConnectionConfigHandlesDeprecatedTimeoutUsageCorrectlyWhenNotUsed(): void
    {
        $config = $this->amqpIntegration->createConnectionConfig([
            'read_timeout' => 678.9,
        ]);

        static::assertSame(TimeoutDeprecationUsageEnum::NOT_USED, $config->getDeprecatedTimeoutCredentialUsage());
        static::assertSame(678.9, $config->getReadTimeout());
    }

    public function testCreateConnectionConfigHandlesDeprecatedTimeoutUsageCorrectlyWhenUsedAlone(): void
    {
        $config = $this->amqpIntegration->createConnectionConfig([
            'timeout' => 123.4,
        ]);

        static::assertSame(TimeoutDeprecationUsageEnum::USED_ALONE, $config->getDeprecatedTimeoutCredentialUsage());
        static::assertSame(123.4, $config->getReadTimeout());
    }

    public function testCreateConnectionConfigHandlesDeprecatedTimeoutUsageCorrectlyWhenShadowed(): void
    {
        $config = $this->amqpIntegration->createConnectionConfig([
            'read_timeout' => 456.7,
            'timeout' => 123.4,
        ]);

        static::assertSame(TimeoutDeprecationUsageEnum::SHADOWED, $config->getDeprecatedTimeoutCredentialUsage());
        static::assertSame(456.7, $config->getReadTimeout());
    }

    public function testGetConfigurationReturnsTheConfiguration(): void
    {
        static::assertSame($this->configuration, $this->amqpIntegration->getConfiguration());
    }

    public function testGetErrorReporterReturnsTheErrorReporter(): void
    {
        static::assertSame($this->errorReporter, $this->amqpIntegration->getErrorReporter());
    }

    public function testGetLoggerReturnsTheLogger(): void
    {
        static::assertSame($this->logger, $this->amqpIntegration->getLogger()->getWrappedLogger());
    }
}
