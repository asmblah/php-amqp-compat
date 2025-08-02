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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\AmqpCompat\Scheduler\Factory;

use Asmblah\PhpAmqpCompat\Driver\Common\Heartbeat\HeartbeatTransmitterInterface;
use Asmblah\PhpAmqpCompat\Scheduler\Factory\NullSchedulerFactory;
use Asmblah\PhpAmqpCompat\Scheduler\Heartbeat\NullHeartbeatScheduler;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;

/**
 * Class NullSchedulerFactoryTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class NullSchedulerFactoryTest extends AbstractTestCase
{
    private NullSchedulerFactory $factory;

    public function setUp(): void
    {
        $this->factory = new NullSchedulerFactory();
    }

    public function testCreateSchedulerReturnsNullHeartbeatScheduler(): void
    {
        $heartbeatTransmitter = mock(HeartbeatTransmitterInterface::class);

        $scheduler = $this->factory->createScheduler($heartbeatTransmitter);

        /** @noinspection PhpConditionAlreadyCheckedInspection */
        static::assertInstanceOf(NullHeartbeatScheduler::class, $scheduler);
    }
}
