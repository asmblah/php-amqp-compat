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

namespace Asmblah\PhpAmqpCompat\Tests\Functional;

use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;

/**
 * Class AbstractFunctionalTestCase.
 *
 * Base class for all functional test cases.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
abstract class AbstractFunctionalTestCase extends AbstractTestCase
{
    private TimerInterface $timeoutTimer;

    public function setUp(): void
    {
        /*
         * ReactPHP loop runs in another Tasque thread via Tasque EventLoop,
         * so we can use it to capture issues with tests that run infinitely, e.g. when a heartbeat
         * is expected to be missed but is not.
         */
        $this->timeoutTimer = Loop::addTimer(60, function () {
            $this->fail('Functional test timed out');
        });
    }

    public function tearDown(): void
    {
        // Make sure we don't allow this timer to leak into subsequent tests.
        Loop::cancelTimer($this->timeoutTimer);
    }
}
