<?php

/*
 * PHP AMQP-Compat - php-amqp/ext-amqp compatibility.
 * Copyright (c) Dan Phillimore (asmblah)
 * https://github.com/asmblah/php-amqp-compat/
 *
 * Released under the MIT license.
 * https://github.com/asmblah/php-amqp-compat/raw/master/MIT-LICENSE.txt
 */

declare(strict_types=1);

namespace Asmblah\PhpAmqpCompat\Integration;

use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridge;
use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Configuration\ConfigurationInterface;
use Asmblah\PhpAmqpCompat\Connection\ConnectionConfig;
use Asmblah\PhpAmqpCompat\Connection\ConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Connection\ConnectorInterface;
use Asmblah\PhpAmqpCompat\Heartbeat\HeartbeatSenderInterface;
use Psr\Log\LoggerInterface;

/**
 * Class AmqpIntegration.
 *
 * This default implementation connects to the AMQP broker via the php-amqplib library.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqpIntegration implements AmqpIntegrationInterface
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly ConnectorInterface $connector,
        private readonly HeartbeatSenderInterface $heartbeatSender,
        ConfigurationInterface $configuration
    ) {
        $this->logger = $configuration->getLogger();
    }

    /**
     * @inheritDoc
     */
    public function connect(ConnectionConfigInterface $config): AmqpConnectionBridgeInterface
    {
        // Open the underlying connection to the AMQP broker via php-amqplib.
        $amqplibConnection = $this->connector->connect($config);

        // Internal representation of the AMQP connection that this compatibility layer uses.
        $connectionBridge = new AmqpConnectionBridge($amqplibConnection);

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
            ConnectionConfigInterface::DEFAULT_HOST;
        $port = array_key_exists('port', $credentials) ?
            (int) $credentials['port'] :
            ConnectionConfigInterface::DEFAULT_PORT;
        $user = array_key_exists('login', $credentials) ?
            (string) $credentials['login'] :
            ConnectionConfigInterface::DEFAULT_USER;
        $password = array_key_exists('password', $credentials) ?
            (string) $credentials['password'] :
            ConnectionConfigInterface::DEFAULT_PASSWORD;
        $virtualHost = array_key_exists('vhost', $credentials) ?
            (string) $credentials['vhost'] :
            ConnectionConfigInterface::DEFAULT_VIRTUAL_HOST;
        $heartbeatInterval = array_key_exists('heartbeat', $credentials) ?
            (int) $credentials['heartbeat'] :
            ConnectionConfigInterface::DEFAULT_HEARTBEAT_INTERVAL;
        $connectionTimeout = array_key_exists('connect_timeout', $credentials) ?
            (float) $credentials['connect_timeout'] :
            ConnectionConfigInterface::DEFAULT_CONNECTION_TIMEOUT;
        $readTimeout = array_key_exists('read_timeout', $credentials) ?
            (float) $credentials['read_timeout'] :
            ConnectionConfigInterface::DEFAULT_READ_TIMEOUT;
        $writeTimeout = array_key_exists('write_timeout', $credentials) ?
            (float) $credentials['write_timeout'] :
            ConnectionConfigInterface::DEFAULT_WRITE_TIMEOUT;
        $rpcTimeout = array_key_exists('rpc_timeout', $credentials) ?
            (float) $credentials['rpc_timeout'] :
            ConnectionConfigInterface::DEFAULT_RPC_TIMEOUT;
        // Note that connection name may explicitly be specified as null.
        $connectionName = isset($credentials['connection_name']) ?
            (string) $credentials['connection_name'] :
            null;

        return new ConnectionConfig(
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
            $connectionName
        );
    }

    /**
     * @inheritDoc
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }
}
