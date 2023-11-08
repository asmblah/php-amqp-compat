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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\AmqpCompat\Error;

use Asmblah\PhpAmqpCompat\Error\ErrorReporter;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;

/**
 * Class ErrorReporterTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ErrorReporterTest extends AbstractTestCase
{
    private ErrorReporter $errorReporter;
    private ?string $triggeredErrorMessage = null;
    private ?int $triggeredErrorNumber = null;

    public function setUp(): void
    {
        set_error_handler(
            $this->errorHandler(...),
            E_USER_DEPRECATED | E_USER_NOTICE | E_USER_WARNING
        );

        $this->errorReporter = new ErrorReporter();
    }

    private function errorHandler(int $number, string $string): bool
    {
        $this->triggeredErrorNumber = $number;
        $this->triggeredErrorMessage = $string;

        return true;
    }

    public function tearDown(): void
    {
        restore_error_handler();
    }

    public function testRaiseDeprecationRaisesDeprecationNotice(): void
    {
        $this->errorReporter->raiseDeprecation('My deprecation message');

        static::assertSame(E_USER_DEPRECATED, $this->triggeredErrorNumber);
        static::assertSame('My deprecation message', $this->triggeredErrorMessage);
    }

    public function testRaiseNoticeRaisesNotice(): void
    {
        $this->errorReporter->raiseNotice('My notice message');

        static::assertSame(E_USER_NOTICE, $this->triggeredErrorNumber);
        static::assertSame('My notice message', $this->triggeredErrorMessage);
    }

    public function testRaiseWarningRaisesWarning(): void
    {
        $this->errorReporter->raiseWarning('My warning message');

        static::assertSame(E_USER_WARNING, $this->triggeredErrorNumber);
        static::assertSame('My warning message', $this->triggeredErrorMessage);
    }
}
