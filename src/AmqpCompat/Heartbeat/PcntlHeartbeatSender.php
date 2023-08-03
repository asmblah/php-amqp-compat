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

namespace Asmblah\PhpAmqpCompat\Heartbeat;

use Asmblah\PhpAmqpCompat\Bridge\Connection\AmqpConnectionBridgeInterface;
use Asmblah\PhpAmqpCompat\Misc\Clock;
use SplObjectStorage;

/**
 * Class PcntlHeartbeatSender.
 *
 * Based on PhpAmqpLib\Connection\Heartbeat\PCNTLHeartbeatSender,
 * with support for multiple simultaneous connections.
 *
 * Uses Unix System V signals with pcntl_async_signals(...) to allow regular heartbeat handling.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class PcntlHeartbeatSender implements HeartbeatSenderInterface
{
    /**
     * @var SplObjectStorage<AmqpConnectionBridgeInterface>
     */
    private SplObjectStorage $connectionBridges;
    private int $interval = 0;

    public function __construct(private Clock $clock)
    {
        $this->connectionBridges = new SplObjectStorage();

        pcntl_async_signals(true);
    }

    private function installSignalHandler(): void
    {
        pcntl_signal(
            SIGALRM,
            function () {
                $now = $this->clock->getUnixTimestamp();

                foreach ($this->connectionBridges as $connectionBridge) {
                    $amqplibConnection = $connectionBridge->getAmqplibConnection();
                    $interval = $connectionBridge->getHeartbeatInterval();

                    if (!$amqplibConnection->isConnected()) {
                        // Connection is no longer open, so we cannot process heartbeats for it.
                        $this->unregister($connectionBridge);
                        continue;
                    }

                    if ($amqplibConnection->isWriting()) {
                        return;
                    }

                    if ($now > ($amqplibConnection->getLastActivity() + $interval)) {
                        $amqplibConnection->checkHeartBeat();
                    }
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

        if ($interval === 0) {
            // Heartbeats are not enabled for the connection.
            return;
        }

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
