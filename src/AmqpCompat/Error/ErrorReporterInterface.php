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
 * Interface ErrorReporterInterface.
 *
 * Handles reporting of warnings/notices etc.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface ErrorReporterInterface
{
    /**
     * Raises a deprecation notice.
     */
    public function raiseDeprecation(string $message): void;

    /**
     * Raises a notice.
     */
    public function raiseNotice(string $message): void;

    /**
     * Raises a warning.
     */
    public function raiseWarning(string $message): void;
}
