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

namespace Asmblah\PhpAmqpCompat\Integration;

use Asmblah\PhpAmqpCompat\Bridge\Channel\EnvelopeTransformerInterface;
use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridge;
use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Configuration\ConfigurationInterface;
use Asmblah\PhpAmqpCompat\Connection\Config\ConnectionConfig;
use Asmblah\PhpAmqpCompat\Connection\Config\ConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Connection\Config\DefaultConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Connection\Config\TimeoutDeprecationUsageEnum;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Exception\ExceptionHandler;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transformer\MessageTransformerInterface;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transport\Transport;
use Asmblah\PhpAmqpCompat\Driver\Common\Exception\ExceptionHandlerInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Transport\TransportConnectorInterface;
use Asmblah\PhpAmqpCompat\Error\ErrorReporterInterface;
use Asmblah\PhpAmqpCompat\Heartbeat\HeartbeatSenderInterface;
use Asmblah\PhpAmqpCompat\Logger\Logger;
use Asmblah\PhpAmqpCompat\Logger\LoggerInterface;

/**
 * Class AmqpIntegration.
 *
 * This default implementation connects to the AMQP broker via the php-amqplib library.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqpIntegration implements AmqpIntegrationInterface
{
    private readonly ErrorReporterInterface $errorReporter;
    private readonly ExceptionHandlerInterface $exceptionHandler;
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly TransportConnectorInterface $connector,
        private readonly HeartbeatSenderInterface $heartbeatSender,
        private readonly ConfigurationInterface $configuration,
        private readonly DefaultConnectionConfigInterface $defaultConnectionConfig,
        private readonly EnvelopeTransformerInterface $envelopeTransformer,
        private readonly MessageTransformerInterface $messageTransformer
    ) {
        $this->errorReporter = $configuration->getErrorReporter();
        $this->logger = new Logger($configuration->getLogger());

        // TODO: Handle with driver setup.
        $this->exceptionHandler = new ExceptionHandler($this->logger);
    }

    /**
     * @inheritDoc
     */
    public function connect(ConnectionConfigInterface $config): AmqpConnectionBridgeInterface
    {
        // TODO: Remove leakage of php-amqplib connection from Transport abstraction here.
        /** @var Transport $transport */
        $transport = $this->connector->connect($config);

        // Internal representation of the AMQP connection that this compatibility layer uses.
        $connectionBridge = new AmqpConnectionBridge(
            $transport->getAmqplibConnection(),
            $transport,
            $config,
            $this->envelopeTransformer,
            $this->messageTransformer,
            $this->errorReporter,
            $this->exceptionHandler,
            $this->logger
        );

        // Install AMQP heartbeat handling (via php-amqplib) as applicable.
        $this->heartbeatSender->register($connectionBridge);

        return $connectionBridge;
    }

    /**
     * @inheritDoc
     */
    public function createConnectionConfig(array $credentials): ConnectionConfigInterface
    {
        $host = array_key_exists('host', $credentials) ?
            (string) $credentials['host'] :
            $this->defaultConnectionConfig->getHost();
        $port = array_key_exists('port', $credentials) ?
            (int) $credentials['port'] :
            $this->defaultConnectionConfig->getPort();
        $user = array_key_exists('login', $credentials) ?
            (string) $credentials['login'] :
            $this->defaultConnectionConfig->getUser();
        $password = array_key_exists('password', $credentials) ?
            (string) $credentials['password'] :
            $this->defaultConnectionConfig->getPassword();
        $virtualHost = array_key_exists('vhost', $credentials) ?
            (string) $credentials['vhost'] :
            $this->defaultConnectionConfig->getVirtualHost();
        $heartbeatInterval = array_key_exists('heartbeat', $credentials) ?
            (int) $credentials['heartbeat'] :
            $this->defaultConnectionConfig->getHeartbeatInterval();
        $maxChannels = array_key_exists('channel_max', $credentials) ?
            (int) $credentials['channel_max'] :
            $this->defaultConnectionConfig->getMaxChannels();
        $maxFrameSize = array_key_exists('frame_max', $credentials) ?
            (int) $credentials['frame_max'] :
            $this->defaultConnectionConfig->getMaxFrameSize();
        $connectionTimeout = array_key_exists('connect_timeout', $credentials) ?
            (float) $credentials['connect_timeout'] :
            $this->defaultConnectionConfig->getConnectionTimeout();
        $writeTimeout = array_key_exists('write_timeout', $credentials) ?
            (float) $credentials['write_timeout'] :
            $this->defaultConnectionConfig->getWriteTimeout();
        $rpcTimeout = array_key_exists('rpc_timeout', $credentials) ?
            (float) $credentials['rpc_timeout'] :
            $this->defaultConnectionConfig->getRpcTimeout();
        // Note that connection name may explicitly be specified as null.
        $connectionName = isset($credentials['connection_name']) ?
            (string) $credentials['connection_name'] :
            null;

        $readTimeoutCredentialUsed = array_key_exists('read_timeout', $credentials);
        $deprecatedTimeoutCredentialUsed = array_key_exists('timeout', $credentials);

        $deprecatedTimeoutCredentialUsage = TimeoutDeprecationUsageEnum::NOT_USED;

        if ($readTimeoutCredentialUsed) {
            if ($deprecatedTimeoutCredentialUsed) {
                $deprecatedTimeoutCredentialUsage = TimeoutDeprecationUsageEnum::SHADOWED;
            }

            $readTimeout = (float)$credentials['read_timeout'];
        } elseif ($deprecatedTimeoutCredentialUsed) {
            $deprecatedTimeoutCredentialUsage = TimeoutDeprecationUsageEnum::USED_ALONE;

            $readTimeout = (float)$credentials['timeout'];
        } else {
            $readTimeout = $this->defaultConnectionConfig->getReadTimeout();
        }

        return new ConnectionConfig(
            $this->defaultConnectionConfig,
            $host,
            $port,
            $user,
            $password,
            $virtualHost,
            $heartbeatInterval,
            $connectionTimeout,
            $readTimeout,
            $writeTimeout,
            $rpcTimeout,
            $maxChannels,
            $maxFrameSize,
            $connectionName,
            $deprecatedTimeoutCredentialUsage
        );
    }

    /**
     * @inheritDoc
     */
    public function getConfiguration(): ConfigurationInterface
    {
        return $this->configuration;
    }

    /**
     * @inheritDoc
     */
    public function getErrorReporter(): ErrorReporterInterface
    {
        return $this->errorReporter;
    }

    /**
     * @inheritDoc
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
}
