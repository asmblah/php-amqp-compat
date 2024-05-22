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
use Asmblah\PhpAmqpCompat\Bridge\Channel\EnvelopeTransformerInterface;
use Asmblah\PhpAmqpCompat\Connection\Config\ConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transformer\MessageTransformerInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Exception\ExceptionHandlerInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Transport\TransportInterface;
use Asmblah\PhpAmqpCompat\Error\ErrorReporterInterface;
use Asmblah\PhpAmqpCompat\Logger\LoggerInterface;
use InvalidArgumentException;
use PhpAmqpLib\Connection\AbstractConnection as AmqplibConnection;
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
        private readonly AmqplibConnection $amqplibConnection,
        private readonly TransportInterface $transport,
        private readonly ConnectionConfigInterface $connectionConfig,
        private readonly EnvelopeTransformerInterface $envelopeTransformer,
        private readonly MessageTransformerInterface $messageTransformer,
        private readonly ErrorReporterInterface $errorReporter,
        private readonly ExceptionHandlerInterface $exceptionHandler,
        private readonly LoggerInterface $logger
    ) {
        $this->channelBridges = new SplObjectStorage();
    }

    /**
     * @inheritDoc
     */
    public function createChannelBridge(): AmqpChannelBridgeInterface
    {
        $amqplibChannel = $this->amqplibConnection->channel();

        $channelBridge = new AmqpChannelBridge($this, $amqplibChannel, new Consumer());

        $this->channelBridges->attach($channelBridge);

        return $channelBridge;
    }

    /**
     * @inheritDoc
     */
    public function getAmqplibConnection(): AmqplibConnection
    {
        return $this->amqplibConnection;
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
    public function getEnvelopeTransformer(): EnvelopeTransformerInterface
    {
        return $this->envelopeTransformer;
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
    public function getExceptionHandler(): ExceptionHandlerInterface
    {
        return $this->exceptionHandler;
    }

    /**
     * @inheritDoc
     */
    public function getHeartbeatInterval(): int
    {
        $timeout = $this->amqplibConnection->getHeartbeat();

        return (int)ceil($timeout / 2);
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
    public function getMessageTransformer(): MessageTransformerInterface
    {
        return $this->messageTransformer;
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
