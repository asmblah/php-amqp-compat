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

namespace Asmblah\PhpAmqpCompat\Connection\Config;

/**
 * Interface ConnectionConfigInterface.
 *
 * Represents the configuration for an upcoming connection that will be made by AMQPConnection.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface ConnectionConfigInterface extends ConnectionConfigProviderInterface
{
    /**
     * Determines whether and how the deprecated 'timeout' credential was used.
     */
    public function getDeprecatedTimeoutCredentialUsage(): TimeoutDeprecationUsageEnum;

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
     * Sets the maximum number of channels that may be open on a connection,
     * overriding any default if used.
     */
    public function setMaxChannels(int $maxChannels): void;

    /**
     * Sets the maximum supported size of a frame in bytes, overriding any default if used.
     */
    public function setMaxFrameSize(int $maxFrameSize): void;

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
     *
     * @return array<mixed>
     */
    public function toLoggableArray(): array;
}
