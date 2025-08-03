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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\AmqpCompat\Misc;

use Asmblah\PhpAmqpCompat\Misc\Clock;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;

/**
 * Class ClockTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ClockTest extends AbstractTestCase
{
    private Clock $clock;

    public function setUp(): void
    {
        $this->clock = new Clock();
    }

    public function testGetUnixTimestampReturnsCurrentTimestamp(): void
    {
        $beforeTime = time();
        usleep(50000);
        $timestamp = $this->clock->getUnixTimestamp();
        usleep(50000);
        $afterTime = time();

        static::assertIsInt($timestamp);
        static::assertGreaterThanOrEqual($beforeTime, $timestamp);
        static::assertLessThanOrEqual($afterTime, $timestamp);
    }
}
