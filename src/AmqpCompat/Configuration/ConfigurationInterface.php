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

namespace Asmblah\PhpAmqpCompat\Configuration;

use Asmblah\PhpAmqpCompat\Error\ErrorReporterInterface;
use Asmblah\PhpAmqpCompat\Heartbeat\HeartbeatSchedulerMode;
use Psr\Log\LoggerInterface;

/**
 * Interface ConfigurationInterface.
 *
 * May be implemented by a custom class and set on the AmqpManager to allow extension.
 *
 * Note that this is different from ConnectionConfig as it allows PHP AMQP-Compat itself to be configured
 * rather than configuring the connection to the AMQP broker.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface ConfigurationInterface
{
    /**
     * Fetches an ErrorReporter to use when raising warnings/notices etc.
     */
    public function getErrorReporter(): ErrorReporterInterface;

    /**
     * Fetches which heartbeat sender mode to use.
     */
    public function getHeartbeatSenderMode(): HeartbeatSchedulerMode;

    /**
     * Fetches a logger to use for additional/internal logging by this library.
     */
    public function getLogger(): LoggerInterface;

    /**
     * Fetches the timeout to use when none has been set, implying no timeout.
     *
     * This is indicated with 0 in php-amqp/ext-amqp, but one must be specified for php-amqplib.
     */
    public function getUnlimitedTimeout(): float;
}
