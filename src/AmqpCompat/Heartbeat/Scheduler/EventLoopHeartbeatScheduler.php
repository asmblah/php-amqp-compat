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

namespace Asmblah\PhpAmqpCompat\Heartbeat\Scheduler;

use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Heartbeat\Transmitter\HeartbeatTransmitterInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use SplObjectStorage;

/**
 * Class EventLoopHeartbeatScheduler.
 *
 * Uses a ReactPHP event loop to allow regular heartbeat scheduling.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class EventLoopHeartbeatScheduler implements HeartbeatSchedulerInterface
{
    /**
     * @var SplObjectStorage<AmqpConnectionBridgeInterface, TimerInterface>
     */
    private SplObjectStorage $connectionBridgeToTimerMap;

    public function __construct(
        private readonly LoopInterface $loop,
        private readonly HeartbeatTransmitterInterface $heartbeatTransmitter
    ) {
        $this->connectionBridgeToTimerMap = new SplObjectStorage();
    }

    /**
     * @inheritDoc
     */
    public function register(AmqpConnectionBridgeInterface $connectionBridge): void
    {
        $interval = $connectionBridge->getHeartbeatInterval();

        $timer = $this->loop->addPeriodicTimer($interval, function () use ($connectionBridge) {
            $this->heartbeatTransmitter->transmit($this, $connectionBridge);
        });

        $this->connectionBridgeToTimerMap->attach($connectionBridge, $timer);
    }

    /**
     * @inheritDoc
     */
    public function unregister(AmqpConnectionBridgeInterface $connectionBridge): void
    {
        $timer = $this->connectionBridgeToTimerMap[$connectionBridge];

        $this->loop->cancelTimer($timer);
    }
}
