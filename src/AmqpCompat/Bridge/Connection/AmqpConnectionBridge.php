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

namespace Asmblah\PhpAmqpCompat\Bridge\Connection;

use Asmblah\PhpAmqpCompat\Bridge\Channel\AmqpChannelBridge;
use Asmblah\PhpAmqpCompat\Bridge\Channel\AmqpChannelBridgeInterface;
use Asmblah\PhpAmqpCompat\Bridge\Channel\Consumer;
use Asmblah\PhpAmqpCompat\Connection\Config\ConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Logger\LoggerInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Transport\TransportInterface;
use Asmblah\PhpAmqpCompat\Error\ErrorReporterInterface;
use Asmblah\PhpAmqpCompat\Exception\TooManyChannelsOnConnectionException;
use InvalidArgumentException;
use SplObjectStorage;

/**
 * Class AmqpConnectionBridge.
 *
 * Defines the internal representation of an AMQP connection for this library.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqpConnectionBridge implements AmqpConnectionBridgeInterface
{
    /**
     * @var SplObjectStorage<AmqpChannelBridgeInterface, null>
     */
    private readonly SplObjectStorage $channelBridges;

    public function __construct(
        private readonly TransportInterface $transport,
        private readonly ConnectionConfigInterface $connectionConfig,
        private readonly ErrorReporterInterface $errorReporter,
        private readonly LoggerInterface $logger
    ) {
        $this->channelBridges = new SplObjectStorage();
    }

    /**
     * @inheritDoc
     */
    public function checkHeartbeat(): void
    {
        $this->transport->checkHeartbeat();
    }

    /**
     * @inheritDoc
     */
    public function createChannelBridge(string $exceptionClass, string $methodName): AmqpChannelBridgeInterface
    {
        $channel = $this->transport->openChannel($exceptionClass, $methodName);

        $channelBridge = new AmqpChannelBridge(
            $this,
            $channel,
            new Consumer()
        );

        if (count($this->channelBridges) === PHP_AMQP_MAX_CHANNELS) {
            throw new TooManyChannelsOnConnectionException(
                sprintf(
                    'Connection already has %d channels open',
                    count($this->channelBridges)
                )
            );
        }

        $this->channelBridges->attach($channelBridge);

        return $channelBridge;
    }

    /**
     * @inheritDoc
     */
    public function disconnect(string $exceptionClass, string $methodName): void
    {
        $this->transport->disconnect($exceptionClass, $methodName);
    }

    /**
     * @inheritDoc
     */
    public function getConnectionConfig(): ConnectionConfigInterface
    {
        return $this->connectionConfig;
    }

    /**
     * @inheritDoc
     */
    public function getErrorReporter(): ErrorReporterInterface
    {
        return $this->errorReporter;
    }

    /**
     * @inheritDoc
     */
    public function getHeartbeatInterval(): int
    {
        return $this->transport->getHeartbeatInterval();
    }

    /**
     * @inheritDoc
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @inheritDoc
     */
    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }

    /**
     * @inheritDoc
     */
    public function getUsedChannels(): int
    {
        return count($this->channelBridges);
    }

    /**
     * @inheritDoc
     */
    public function isBusy(): bool
    {
        return $this->transport->isBusy();
    }

    /**
     * @inheritDoc
     */
    public function isConnected(): bool
    {
        return $this->transport->isConnected();
    }

    /**
     * @inheritDoc
     */
    public function setReadTimeout(float $seconds): void
    {
        $this->transport->setReadTimeout($seconds);
    }

    /**
     * @inheritDoc
     */
    public function unregisterChannelBridge(AmqpChannelBridgeInterface $channelBridge): void
    {
        if (!$this->channelBridges->contains($channelBridge)) {
            throw new InvalidArgumentException(
                __METHOD__ . '(): Channel bridge is not registered'
            );
        }

        $this->channelBridges->detach($channelBridge);
    }
}
