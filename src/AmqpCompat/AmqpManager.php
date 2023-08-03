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

use Asmblah\PhpAmqpCompat\Configuration\Configuration;
use Asmblah\PhpAmqpCompat\Configuration\ConfigurationInterface;
use Asmblah\PhpAmqpCompat\Connection\Amqplib\ConnectionFactory;
use Asmblah\PhpAmqpCompat\Connection\Config\DefaultConnectionConfig;
use Asmblah\PhpAmqpCompat\Connection\Connector;
use Asmblah\PhpAmqpCompat\Heartbeat\PcntlHeartbeatSender;
use Asmblah\PhpAmqpCompat\Integration\AmqpIntegration;
use Asmblah\PhpAmqpCompat\Integration\AmqpIntegrationInterface;
use Asmblah\PhpAmqpCompat\Misc\Clock;
use LogicException;

/**
 * Class AmqpManager.
 *
 * Allows the AmqpIntegration to be replaced while supporting ext-amqp's API
 * that does not allow for dependency injection, providing the default implementation otherwise.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqpManager
{
    private static ?AmqpIntegrationInterface $amqpIntegration = null;
    private static ?ConfigurationInterface $configuration = null;

    /**
     * Fetches the AmqpIntegration to use. Will create one by default if not overridden.
     */
    public static function getAmqpIntegration(): AmqpIntegrationInterface
    {
        if (self::$amqpIntegration === null) {
            $configuration = self::getConfiguration();

            self::$amqpIntegration = new AmqpIntegration(
                new Connector(
                    new ConnectionFactory(),
                    $configuration->getUnlimitedTimeout()
                ),
                new PcntlHeartbeatSender(new Clock()),
                $configuration,
                new DefaultConnectionConfig()
            );
        }

        return self::$amqpIntegration;
    }

    /**
     * Fetches the Configuration to use. Will create one by default if not overridden.
     */
    public static function getConfiguration(): ConfigurationInterface
    {
        if (self::$configuration === null) {
            self::$configuration = new Configuration();
        }

        return self::$configuration;
    }

    /**
     * Overrides the AmqpIntegration to use.
     *
     * If null is given, the default implementation will be used.
     */
    public static function setAmqpIntegration(?AmqpIntegrationInterface $amqpIntegration): void
    {
        self::$amqpIntegration = $amqpIntegration;
    }

    /**
     * Overrides the Configuration to use.
     *
     * If null is given, the default implementation will be used.
     */
    public static function setConfiguration(?ConfigurationInterface $configuration): void
    {
        if (self::$amqpIntegration !== null) {
            // Raise an error, because the configuration would not be used by the current AmqpIntegration
            // which would be inconsistent.
            throw new LogicException('Cannot set configuration while an AmqpIntegration has already been set');
        }

        self::$configuration = $configuration;
    }
}
