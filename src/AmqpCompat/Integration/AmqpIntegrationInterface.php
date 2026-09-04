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

namespace Asmblah\PhpAmqpCompat\Integration;

use AMQPConnectionException;
use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Configuration\ConfigurationInterface;
use Asmblah\PhpAmqpCompat\Connection\Config\ConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Logger\LoggerInterface;
use Asmblah\PhpAmqpCompat\Error\ErrorReporterInterface;

/**
 * Interface AmqpIntegrationInterface.
 *
 * May be implemented by a custom class and set on the AmqpManager to allow extension.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface AmqpIntegrationInterface
{
    /**
     * Connects to the AMQP server.
     *
     * @throws AMQPConnectionException
     */
    public function connect(ConnectionConfigInterface $config, string $methodName): AmqpConnectionBridgeInterface;

    /**
     * Creates a configuration for later connection.
     *
     * @param array<mixed> $credentials
     */
    public function createConnectionConfig(array $credentials): ConnectionConfigInterface;

    /**
     * Fetches the configuration for this library.
     */
    public function getConfiguration(): ConfigurationInterface;

    /**
     * Fetches an ErrorReporter to use when raising warnings/notices etc.
     */
    public function getErrorReporter(): ErrorReporterInterface;

    /**
     * Fetches a logger to use for additional/internal logging by this library.
     *
     * @return LoggerInterface
     */
    public function getLogger(): LoggerInterface;
}
