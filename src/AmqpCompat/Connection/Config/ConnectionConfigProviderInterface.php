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
 * Interface ConnectionConfigProviderInterface.
 *
 * Represents the configuration for an upcoming connection that will be made by AMQPConnection.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface ConnectionConfigProviderInterface
{
    /**
     * Fetches the configured name for the connection, or null if none was given.
     */
    public function getConnectionName(): ?string;

    /**
     * Fetches the configured connection timeout (in seconds), or the default if none was given.
     */
    public function getConnectionTimeout(): float;

    /**
     * Determines whether and how the deprecated 'amqp.timeout' INI setting was used.
     */
    public function getDeprecatedTimeoutIniSettingUsage(): TimeoutDeprecationUsageEnum;

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
}
