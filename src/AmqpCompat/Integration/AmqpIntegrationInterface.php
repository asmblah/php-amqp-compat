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

use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Connection\Config\ConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Error\ErrorReporterInterface;
use Exception;
use Psr\Log\LoggerInterface;

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
     * @throws Exception
     */
    public function connect(ConnectionConfigInterface $config): AmqpConnectionBridgeInterface;

    /**
     * Creates a configuration for later connection.
     */
    public function createConnectionConfig(array $credentials): ConnectionConfigInterface;

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
