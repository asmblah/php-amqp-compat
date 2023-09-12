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

    public function __construct(
        private readonly ContextResolver $contextResolver
    ) {
    }

    /**
     * Fetches the userland file & line context of the actual test file as would be reported by php-amqp/ext-amqp.
     */
    private function getContext(): string
    {
        $context = $this->contextResolver->getContext();

        $file = $context['file'] ?? '(unknown)';
        $line = $context['line'] ?? '(unknown)';

        return ' in ' . $file . ' on line ' . $line;
    }

    private function handleSeparator(): void
    {
        $anyOutputHasBeenSent = headers_sent();

        if ($this->messagesCount > 0 || $anyOutputHasBeenSent) {
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

        print 'Deprecated: ' . $message . $this->getContext() . PHP_EOL;
    }

    /**
     * @inheritDoc
     */
    public function raiseNotice(string $message): void
    {
        $this->handleSeparator();

        print 'Notice: ' . $message . $this->getContext() . PHP_EOL;
    }

    /**
     * @inheritDoc
     */
    public function raiseWarning(string $message): void
    {
        $this->handleSeparator();

        print 'Warning: ' . $message . $this->getContext() . PHP_EOL;
    }
}
