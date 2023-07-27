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

namespace Asmblah\PhpAmqpCompat;

use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridge;
use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Connection\ConnectionConfig;
use Asmblah\PhpAmqpCompat\Connection\ConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Connection\ConnectorInterface;
use Asmblah\PhpAmqpCompat\Heartbeat\HeartbeatSenderInterface;

/**
 * Class AmqpFactory.
 *
 * This default implementation connects to the AMQP broker via the php-amqplib library.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqpFactory implements AmqpFactoryInterface
{
    public function __construct(
        private readonly ConnectorInterface $connector,
        private readonly HeartbeatSenderInterface $heartbeatSender
    ) {
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
            'localhost';
        $port = array_key_exists('port', $credentials) ?
            (int) $credentials['port'] :
            5672;
        $user = array_key_exists('login', $credentials) ?
            (string) $credentials['login'] :
            'guest';
        $password = array_key_exists('password', $credentials) ?
            (string) $credentials['password'] :
            'guest';
        $virtualHost = array_key_exists('vhost', $credentials) ?
            (string) $credentials['vhost'] :
            '/';
        $heartbeatInterval = array_key_exists('heartbeat', $credentials) ?
            (int) $credentials['heartbeat'] :
            0;
        $connectionTimeout = array_key_exists('connect_timeout', $credentials) ?
            (float) $credentials['connect_timeout'] :
            3.0;
        $readTimeout = array_key_exists('read_timeout', $credentials) ?
            (float) $credentials['read_timeout'] :
            3.0;
        $writeTimeout = array_key_exists('write_timeout', $credentials) ?
            (float) $credentials['write_timeout'] :
            3.0;
        $rpcTimeout = array_key_exists('rpc_timeout', $credentials) ?
            (float) $credentials['rpc_timeout'] :
            0.0;

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
            $rpcTimeout
        );
    }
}
