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

namespace Asmblah\PhpAmqpCompat\Scheduler\Heartbeat;

use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Heartbeat\HeartbeatTransmitterInterface;
use SplObjectStorage;

/**
 * Class PcntlHeartbeatScheduler.
 *
 * Based on PhpAmqpLib\Connection\Heartbeat\PCNTLHeartbeatSender,
 * with support for multiple simultaneous connections.
 *
 * Uses Unix System V signals with `pcntl_async_signals(...)` to allow regular heartbeat scheduling.
 *
 * Note:
 * - EventLoop is preferred as signals can interrupt sleep functions, stream_select() etc.
 *   and so this pcntl scheduler is no longer recommended when heartbeat interval is set to a low (frequent) value.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class PcntlHeartbeatScheduler implements HeartbeatSchedulerInterface
{
    /**
     * @var SplObjectStorage<AmqpConnectionBridgeInterface, null>
     */
    private SplObjectStorage $connectionBridges;
    private int $interval = 0;

    public function __construct(
        private readonly HeartbeatTransmitterInterface $heartbeatTransmitter
    ) {
        $this->connectionBridges = new SplObjectStorage();

        pcntl_async_signals(true);
    }

    private function installSignalHandler(): void
    {
        pcntl_signal(
            SIGALRM,
            function () {
                foreach ($this->connectionBridges as $connectionBridge) {
                    $this->heartbeatTransmitter->transmit($this, $connectionBridge);
                }

                // Set alarm signal to be triggered after the most frequent interval elapses.
                pcntl_alarm($this->interval);
            },
            true
        );

        pcntl_alarm($this->interval);
    }

    /**
     * @inheritDoc
     */
    public function register(AmqpConnectionBridgeInterface $connectionBridge): void
    {
        $interval = $connectionBridge->getHeartbeatInterval();

        $this->connectionBridges->attach($connectionBridge);

        if ($this->interval > 0 && $interval >= $this->interval) {
            // Signal handler is already installed at a more regular interval,
            // so there is no need to change it.
            return;
        }

        $this->interval = $interval;

        $this->installSignalHandler();
    }

    /**
     * @inheritDoc
     */
    public function unregister(AmqpConnectionBridgeInterface $connectionBridge): void
    {
        $this->connectionBridges->detach($connectionBridge);

        if (count($this->connectionBridges) === 0) {
            // No more connections remain.

            // Restore the default signal handler.
            pcntl_signal(SIGALRM, SIG_IGN);
        }
    }
}
