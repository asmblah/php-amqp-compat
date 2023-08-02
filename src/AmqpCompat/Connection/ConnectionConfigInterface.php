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

namespace Asmblah\PhpAmqpCompat\Connection;

/**
 * Interface ConnectionConfigInterface.
 *
 * Represents the configuration for an upcoming connection that will be made by AMQPConnection.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface ConnectionConfigInterface
{
    public const DEFAULT_CONNECTION_TIMEOUT = 0.0;
    public const DEFAULT_HEARTBEAT_INTERVAL = 0;
    public const DEFAULT_HOST = 'localhost';
    public const DEFAULT_PASSWORD = 'guest';
    public const DEFAULT_PORT = 5672;
    public const DEFAULT_READ_TIMEOUT = 0.0;
    public const DEFAULT_RPC_TIMEOUT = 0.0;
    public const DEFAULT_USER = 'guest';
    public const DEFAULT_VIRTUAL_HOST = '/';
    public const DEFAULT_WRITE_TIMEOUT = 0.0;

    /**
     * Fetches the configured name for the connection, or null if none was given.
     */
    public function getConnectionName(): ?string;

    /**
     * Fetches the configured connection timeout (in seconds), or the default if none was given.
     */
    public function getConnectionTimeout(): float;

    /**
     * Fetches the configured heartbeat interval in seconds.
     */
    public function getHeartbeatInterval(): int;

    /**
     * Fetches the configured hostname, or the default if none was given.
     */
    public function getHost(): string;

    /**
     * Fetches the configured password, or the default if none was given.
     */
    public function getPassword(): string;

    /**
     * Fetches the configured port, or the default if none was given.
     */
    public function getPort(): int;

    /**
     * Fetches the configured read timeout (in seconds), or the default if none was given.
     */
    public function getReadTimeout(): float;

    /**
     * Fetches the configured RPC timeout (in seconds), or the default if none was given.
     */
    public function getRpcTimeout(): float;

    /**
     * Fetches the configured username, or the default if none was given.
     */
    public function getUser(): string;

    /**
     * Fetches the configured virtual host (vhost) to connect to on the AMQP broker,
     * or the default if none was given.
     */
    public function getVirtualHost(): string;

    /**
     * Fetches the configured write timeout (in seconds), or the default if none was given.
     */
    public function getWriteTimeout(): float;

    /**
     * Sets the configured name for the connection.
     */
    public function setConnectionName(?string $connectionName): void;

    /**
     * Sets the configured connection timeout (in seconds), overriding any default if used.
     */
    public function setConnectionTimeout(float $timeout): void;

    /**
     * Sets the configured heartbeat interval in seconds.
     */
    public function setHeartbeatInterval(int $interval): void;

    /**
     * Sets the configured hostname, overriding any default if used.
     */
    public function setHost(string $host): void;

    /**
     * Sets the configured password, overriding any default if used.
     */
    public function setPassword(string $password): void;

    /**
     * Sets the configured port, overriding any default if used.
     */
    public function setPort(int $port): void;

    /**
     * Sets the configured read timeout (in seconds), overriding any default if used.
     */
    public function setReadTimeout(float $timeout): void;

    /**
     * Sets the configured RPC timeout (in seconds), overriding any default if used.
     */
    public function setRpcTimeout(float $timeout): void;

    /**
     * Sets the configured username, overriding any default if used.
     */
    public function setUser(string $user): void;

    /**
     * Sets the configured virtual host (vhost) to connect to on the AMQP broker,
     * overriding any default if used.
     */
    public function setVirtualHost(string $virtualHost): void;

    /**
     * Sets the configured write timeout (in seconds), overriding any default if used.
     */
    public function setWriteTimeout(float $timeout): void;

    /**
     * Fetches the config structure as an array suitable for logging, with sensitive credentials obfuscated.
     */
    public function toLoggableArray(): array;
}
