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

namespace Asmblah\PhpAmqpCompat;

use Asmblah\PhpAmqpCompat\Bridge\Channel\EnvelopeTransformer;
use Asmblah\PhpAmqpCompat\Configuration\Configuration;
use Asmblah\PhpAmqpCompat\Configuration\ConfigurationInterface;
use Asmblah\PhpAmqpCompat\Connection\Amqplib\ConnectionFactory;
use Asmblah\PhpAmqpCompat\Connection\Config\DefaultConnectionConfig;
use Asmblah\PhpAmqpCompat\Connection\Connector;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Heartbeat\HeartbeatTransmitter;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Processor\ValueProcessor;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transformer\MessageTransformer;
use Asmblah\PhpAmqpCompat\Heartbeat\HeartbeatSender;
use Asmblah\PhpAmqpCompat\Integration\AmqpIntegration;
use Asmblah\PhpAmqpCompat\Integration\AmqpIntegrationInterface;
use Asmblah\PhpAmqpCompat\Misc\Clock;
use Asmblah\PhpAmqpCompat\Misc\Ini;

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
            $valueProcessor = new ValueProcessor();

            $heartbeatTransmitter = new HeartbeatTransmitter(new Clock());

            $heartbeatScheduler = $configuration->getSchedulerFactory()->createScheduler($heartbeatTransmitter);

            self::$amqpIntegration = new AmqpIntegration(
                new Connector(
                    new ConnectionFactory(),
                    $configuration->getUnlimitedTimeout()
                ),
                new HeartbeatSender($heartbeatScheduler),
                $configuration,
                new DefaultConnectionConfig(new Ini()),
                new EnvelopeTransformer($valueProcessor),
                new MessageTransformer($valueProcessor)
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
        self::$configuration = $amqpIntegration?->getConfiguration();
    }

    /**
     * Overrides the Configuration to use.
     *
     * If null is given, the default implementation will be used.
     */
    public static function setConfiguration(?ConfigurationInterface $configuration): void
    {
        self::$configuration = $configuration;

        // Clear any current integration, otherwise the configuration may not be used by it
        // which would be inconsistent.
        self::$amqpIntegration = null;
    }
}
