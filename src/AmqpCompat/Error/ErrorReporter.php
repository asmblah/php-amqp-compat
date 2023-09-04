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

namespace Asmblah\PhpAmqpCompat\Error;

/**
 * Class ErrorReporter.
 *
 * Default implementation that handles reporting of warnings/notices etc.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ErrorReporter implements ErrorReporterInterface
{
    /**
     * @inheritDoc
     */
    public function raiseDeprecation(string $message): void
    {
        trigger_error($message, E_USER_DEPRECATED);
    }

    /**
     * @inheritDoc
     */
    public function raiseNotice(string $message): void
    {
        trigger_error($message, E_USER_NOTICE);
    }

    /**
     * @inheritDoc
     */
    public function raiseWarning(string $message): void
    {
        trigger_error($message, E_USER_WARNING);
    }
}
