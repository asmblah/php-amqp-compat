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

use Asmblah\PhpAmqpCompat\Misc\IniInterface;
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
    private TimeoutDeprecationUsageEnum $deprecatedTimeoutIniSettingUsage = TimeoutDeprecationUsageEnum::NOT_USED;

    public function __construct(private readonly IniInterface $ini)
    {
    }

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
    public function getDeprecatedTimeoutIniSettingUsage(): TimeoutDeprecationUsageEnum
    {
        return $this->deprecatedTimeoutIniSettingUsage;
    }

    /**
     * @inheritDoc
     */
    public function getGlobalPrefetchCount(): int
    {
        $iniSetting = $this->getIniSetting('amqp.global_prefetch_count');

        return $iniSetting !== null ?
            (int)$iniSetting :
            static::DEFAULT_GLOBAL_PREFETCH_COUNT;
    }

    /**
     * @inheritDoc
     */
    public function getGlobalPrefetchSize(): int
    {
        $iniSetting = $this->getIniSetting('amqp.global_prefetch_size');

        return $iniSetting !== null ?
            (int)$iniSetting :
            static::DEFAULT_GLOBAL_PREFETCH_SIZE;
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
        $iniSetting = $this->ini->getRawIniSetting($name);

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
    public function getMaxChannels(): int
    {
        $iniSetting = $this->getIniSetting('amqp.channel_max');

        return $iniSetting !== null ?
            (int)$iniSetting :
            static::DEFAULT_MAX_CHANNELS;
    }

    /**
     * @inheritDoc
     */
    public function getMaxFrameSize(): int
    {
        $iniSetting = $this->getIniSetting('amqp.frame_max');

        return $iniSetting !== null ?
            (int)$iniSetting :
            static::DEFAULT_MAX_FRAME_SIZE;
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
    public function getPrefetchCount(): int
    {
        $iniSetting = $this->getIniSetting('amqp.prefetch_count');

        return $iniSetting !== null ?
            (int)$iniSetting :
            static::DEFAULT_PREFETCH_COUNT;
    }

    /**
     * @inheritDoc
     */
    public function getPrefetchSize(): int
    {
        $iniSetting = $this->getIniSetting('amqp.prefetch_size');

        return $iniSetting !== null ?
            (int)$iniSetting :
            static::DEFAULT_PREFETCH_SIZE;
    }

    /**
     * @inheritDoc
     */
    public function getReadTimeout(): float
    {
        $deprecatedIniSetting = $this->getIniSetting('amqp.timeout');
        $readTimeoutIniSetting = $this->getIniSetting('amqp.read_timeout');

        if ($readTimeoutIniSetting && $deprecatedIniSetting) {
            $this->deprecatedTimeoutIniSettingUsage = TimeoutDeprecationUsageEnum::SHADOWED;

            return (float)$readTimeoutIniSetting;
        }

        if ($readTimeoutIniSetting) {
            return (float)$readTimeoutIniSetting;
        }

        if ($deprecatedIniSetting) {
            $this->deprecatedTimeoutIniSettingUsage = TimeoutDeprecationUsageEnum::USED_ALONE;

            return (float)$deprecatedIniSetting;
        }

        return static::DEFAULT_READ_TIMEOUT;
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
