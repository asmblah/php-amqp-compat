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

namespace Asmblah\PhpAmqpCompat\Connection\Config;

/**
 * Class ConnectionConfig.
 *
 * Represents the configuration for an upcoming connection that will be made by AMQPConnection.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ConnectionConfig implements ConnectionConfigInterface
{
    /**
     * Heartbeat interval in seconds.
     */
    private int $heartbeatInterval;
    /**
     * Hostname of the AMQP broker to connect to.
     */
    private string $host;
    /**
     * Password to authenticate with the AMQP broker with.
     */
    private string $password;
    /**
     * Port of the AMQP broker to connect to.
     */
    private int $port;
    /**
     * Username to authenticate with the AMQP broker as.
     */
    private string $user;
    private string $virtualHost;

    public function __construct(
        string $host = DefaultConnectionConfigInterface::DEFAULT_HOST,
        int $port = DefaultConnectionConfigInterface::DEFAULT_PORT,
        string $user = DefaultConnectionConfigInterface::DEFAULT_USER,
        string $password = DefaultConnectionConfigInterface::DEFAULT_PASSWORD,
        string $virtualHost = DefaultConnectionConfigInterface::DEFAULT_VIRTUAL_HOST,
        int $heartbeatInterval = DefaultConnectionConfigInterface::DEFAULT_HEARTBEAT_INTERVAL,
        /**
         * Timeout (in seconds) to wait for the AMQP connection to open.
         */
        private float $connectionTimeout = DefaultConnectionConfigInterface::DEFAULT_CONNECTION_TIMEOUT,
        /**
         * Timeout (in seconds) to wait for incoming activity from the AMQP broker.
         */
        private float $readTimeout = DefaultConnectionConfigInterface::DEFAULT_READ_TIMEOUT,
        /**
         * Timeout (in seconds) to wait for outgoing activity to the AMQP broker.
         */
        private float $writeTimeout = DefaultConnectionConfigInterface::DEFAULT_WRITE_TIMEOUT,
        /**
         * Timeout (in seconds) to wait for RPC.
         */
        private float $rpcTimeout = DefaultConnectionConfigInterface::DEFAULT_RPC_TIMEOUT,
        /**
         * Optional name for the connection, null if none.
         */
        private ?string $connectionName = null
    ) {
        $this->heartbeatInterval = $heartbeatInterval;
        $this->host = $host;
        $this->password = $password;
        $this->port = $port;
        $this->user = $user;
        $this->virtualHost = $virtualHost;
    }

    /**
     * @inheritDoc
     */
    public function getConnectionName(): ?string
    {
        return $this->connectionName;
    }

    /**
     * @inheritDoc
     */
    public function getConnectionTimeout(): float
    {
        return $this->connectionTimeout;
    }

    /**
     * @inheritDoc
     */
    public function getHeartbeatInterval(): int
    {
        return $this->heartbeatInterval;
    }

    /**
     * @inheritDoc
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @inheritDoc
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @inheritDoc
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @inheritDoc
     */
    public function getReadTimeout(): float
    {
        return $this->readTimeout;
    }

    /**
     * @inheritDoc
     */
    public function getRpcTimeout(): float
    {
        return $this->rpcTimeout;
    }

    /**
     * @inheritDoc
     */
    public function getUser(): string
    {
        return $this->user;
    }

    /**
     * @inheritDoc
     */
    public function getVirtualHost(): string
    {
        return $this->virtualHost;
    }

    /**
     * @inheritDoc
     */
    public function getWriteTimeout(): float
    {
        return $this->writeTimeout;
    }

    /**
     * @inheritDoc
     */
    public function setConnectionName(?string $connectionName): void
    {
        $this->connectionName = $connectionName;
    }

    /**
     * @inheritDoc
     */
    public function setConnectionTimeout(float $timeout): void
    {
        $this->connectionTimeout = $timeout;
    }

    /**
     * @inheritDoc
     */
    public function setHeartbeatInterval(int $interval): void
    {
        $this->heartbeatInterval = $interval;
    }

    /**
     * @inheritDoc
     */
    public function setHost(string $host): void
    {
        $this->host = $host;
    }

    /**
     * @inheritDoc
     */
    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    /**
     * @inheritDoc
     */
    public function setPort(int $port): void
    {
        $this->port = $port;
    }

    /**
     * @inheritDoc
     */
    public function setReadTimeout(float $timeout): void
    {
        $this->readTimeout = $timeout;
    }

    /**
     * @inheritDoc
     */
    public function setRpcTimeout(float $timeout): void
    {
        $this->rpcTimeout = $timeout;
    }

    /**
     * @inheritDoc
     */
    public function setUser(string $user): void
    {
        $this->user = $user;
    }

    /**
     * @inheritDoc
     */
    public function setVirtualHost(string $virtualHost): void
    {
        $this->virtualHost = $virtualHost;
    }

    /**
     * @inheritDoc
     */
    public function setWriteTimeout(float $timeout): void
    {
        $this->writeTimeout = $timeout;
    }

    /**
     * @inheritDoc
     */
    public function toLoggableArray(): array
    {
        return [
            'connection_name' => $this->connectionName,
            'connection_timeout' => $this->connectionTimeout,
            'heartbeat_interval' => $this->heartbeatInterval,
            'host' => $this->host,
            // Obfuscate the password as it is sensitive.
            'password' => str_pad(substr($this->password, 0, 2), 8, '*'),
            'port' => $this->port,
            'read_timeout' => $this->readTimeout,
            'rpc_timeout' => $this->rpcTimeout,
            'user' => $this->user,
            'virtual_host' => $this->virtualHost,
            'write_timeout' => $this->writeTimeout,
        ];
    }
}
