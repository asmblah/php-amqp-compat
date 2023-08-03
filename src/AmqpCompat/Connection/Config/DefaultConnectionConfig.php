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

use RuntimeException;

/**
 * Class DefaultConnectionConfig.
 *
 * Represents the default configuration for an upcoming connection
 * that will be made by AMQPConnection, which will use INI settings.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class DefaultConnectionConfig implements DefaultConnectionConfigInterface
{
    /**
     * @inheritDoc
     */
    public function getConnectionName(): ?string
    {
        return null; // Cannot be configured by INI setting.
    }

    /**
     * @inheritDoc
     */
    public function getConnectionTimeout(): float
    {
        $iniSetting = $this->getIniSetting('amqp.connect_timeout');

        return $iniSetting !== null ?
            (float)$iniSetting :
            static::DEFAULT_CONNECTION_TIMEOUT;
    }

    /**
     * @inheritDoc
     */
    public function getHeartbeatInterval(): int
    {
        $iniSetting = $this->getIniSetting('amqp.heartbeat');

        return $iniSetting !== null ?
            (int)$iniSetting :
            static::DEFAULT_HEARTBEAT_INTERVAL;
    }

    /**
     * @inheritDoc
     */
    public function getHost(): string
    {
        $iniSetting = $this->getIniSetting('amqp.host');

        return $iniSetting !== null ?
            $iniSetting :
            static::DEFAULT_HOST;
    }

    private function getIniSetting(string $name): ?string
    {
        /*
         * TODO: Because we are not registering an actual PHP extension, the amqp.* INI settings
         *       are not registered. This means we cannot use ini_get(...) nor ini_set(...) for these settings,
         *       false will be returned instead.
         *       We can partially work around this by reading the unprocessed settings with get_cfg_var(...),
         *       however this still means the settings cannot be read at runtime with ini_get(...)
         *       nor changed at runtime with ini_set(...).
         */
        $iniSetting = get_cfg_var($name);

        if ($iniSetting === false) {
            return null;
        }

        if (is_array($iniSetting)) {
            throw new RuntimeException(__METHOD__ . '() :: Array INI settings not supported');
        }

        return $iniSetting;
    }

    /**
     * @inheritDoc
     */
    public function getPassword(): string
    {
        $iniSetting = $this->getIniSetting('amqp.password');

        return $iniSetting !== null ?
            $iniSetting :
            static::DEFAULT_PASSWORD;
    }

    /**
     * @inheritDoc
     */
    public function getPort(): int
    {
        $iniSetting = $this->getIniSetting('amqp.port');

        return $iniSetting !== null ?
            (int)$iniSetting :
            static::DEFAULT_PORT;
    }

    /**
     * @inheritDoc
     */
    public function getReadTimeout(): float
    {
        $iniSetting = $this->getIniSetting('amqp.read_timeout');

        return $iniSetting !== null ?
            (float)$iniSetting :
            static::DEFAULT_READ_TIMEOUT;
    }

    /**
     * @inheritDoc
     */
    public function getRpcTimeout(): float
    {
        $iniSetting = $this->getIniSetting('amqp.rpc_timeout');

        return $iniSetting !== null ?
            (float)$iniSetting :
            static::DEFAULT_RPC_TIMEOUT;
    }

    /**
     * @inheritDoc
     */
    public function getUser(): string
    {
        $iniSetting = $this->getIniSetting('amqp.login');

        return $iniSetting !== null ?
            $iniSetting :
            static::DEFAULT_USER;
    }

    /**
     * @inheritDoc
     */
    public function getVirtualHost(): string
    {
        $iniSetting = $this->getIniSetting('amqp.vhost');

        return $iniSetting !== null ?
            $iniSetting :
            static::DEFAULT_VIRTUAL_HOST;
    }

    /**
     * @inheritDoc
     */
    public function getWriteTimeout(): float
    {
        $iniSetting = $this->getIniSetting('amqp.write_timeout');

        return $iniSetting !== null ?
            (float)$iniSetting :
            static::DEFAULT_WRITE_TIMEOUT;
    }
}
