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

namespace Asmblah\PhpAmqpCompat\Tests\Functional\Reference\Util;

use Asmblah\PhpAmqpCompat\Error\ErrorReporterInterface;

/**
 * Class TestErrorReporter.
 *
 * Modifies error message contexts to allow the reference implementation php-amqp/ext-amqp's
 * own test suite to be run against this library.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class TestErrorReporter implements ErrorReporterInterface
{
    private int $messagesCount = 0;

    private function handleSeparator(): void
    {
        if ($this->messagesCount > 0) {
            print PHP_EOL;
        }

        $this->messagesCount++;
    }

    /**
     * @inheritDoc
     */
    public function raiseDeprecation(string $message): void
    {
        $this->handleSeparator();

        print 'Deprecated: ' . $message . ' in amqp.so on line 2' . PHP_EOL;
    }

    /**
     * @inheritDoc
     */
    public function raiseNotice(string $message): void
    {
        $this->handleSeparator();

        print 'Notice: ' . $message . ' in amqp.so on line 2' . PHP_EOL;
    }

    /**
     * @inheritDoc
     */
    public function raiseWarning(string $message): void
    {
        $this->handleSeparator();

        print 'Warning: ' . $message . ' in amqp.so on line 2' . PHP_EOL;
    }
}
