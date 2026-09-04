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

namespace Asmblah\PhpAmqpCompat\Driver\Amqplib\Transport;

use Asmblah\PhpAmqpCompat\Driver\Amqplib\Channel\Channel;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transformer\EnvelopeTransformerInterface;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transformer\MessageTransformerInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Channel\ChannelInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Exception\ExceptionHandlerInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Transport\TransportInterface;
use Asmblah\PhpAmqpCompat\Exception\HeartbeatMissedException;
use Asmblah\PhpAmqpCompat\Exception\SocketConfigurationFailedException;
use Asmblah\PhpAmqpCompat\Exception\TransportConfigurationFailedException;
use Asmblah\PhpAmqpCompat\Misc\ClockInterface;
use Asmblah\PhpAmqpCompat\Socket\SocketSubsystemInterface;
use Closure;
use Exception;
use PhpAmqpLib\Connection\AbstractConnection as AmqplibConnection;
use PhpAmqpLib\Exception\AMQPExceptionInterface;
use PhpAmqpLib\Exception\AMQPHeartbeatMissedException;
use PhpAmqpLib\Wire\IO\StreamIO;
use RuntimeException;
use Socket;

/**
 * Class Transport.
 *
 * Manages the connection with the underlying php-amqplib driver.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class Transport implements TransportInterface
{
    public function __construct(
        private readonly AmqplibConnection $amqplibConnection,
        private readonly SocketSubsystemInterface $socketSubsystem,
        private readonly ClockInterface $clock,
        private readonly ExceptionHandlerInterface $exceptionHandler,
        private readonly EnvelopeTransformerInterface $envelopeTransformer,
        private readonly MessageTransformerInterface $messageTransformer
    ) {
    }

    /**
     * @inheritDoc
     */
    public function checkHeartbeat(): void
    {
        $now = $this->clock->getUnixTimestamp();

        $interval = $this->getHeartbeatInterval();

        if ($now > ($this->amqplibConnection->getLastActivity() + $interval)) {
            try {
                $this->amqplibConnection->checkHeartBeat();
            } catch (AMQPHeartbeatMissedException $exception) {
                // TODO: Verify message is correct vs. original.
                throw new HeartbeatMissedException(
                    'Heartbeat missed: ' . $exception->getMessage(),
                    previous: $exception
                );
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function disconnect(string $exceptionClass, string $methodName): void
    {
        try {
            $this->amqplibConnection->close();
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, $exceptionClass, $methodName);
        }
    }

    /**
     * Fetches the underlying php-amqplib connection.
     */
    public function getAmqplibConnection(): AmqplibConnection
    {
        return $this->amqplibConnection;
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
    public function isBusy(): bool
    {
        return $this->amqplibConnection->isWriting();
    }

    /**
     * @inheritDoc
     */
    public function isConnected(): bool
    {
        return $this->amqplibConnection->isConnected();
    }

    /**
     * @inheritDoc
     */
    public function openChannel(string $exceptionClass, string $methodName): ChannelInterface
    {
        try {
            $amqplibChannel = $this->amqplibConnection->channel();
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, $exceptionClass, $methodName);
        }

        return new Channel(
            $this,
            $amqplibChannel,
            $this->exceptionHandler,
            $this->envelopeTransformer,
            $this->messageTransformer
        );
    }

    /**
     * @inheritDoc
     */
    public function setReadTimeout(float $seconds): void
    {
        $io = $this->amqplibConnection->getIO();

        if (!$io instanceof StreamIO) {
            throw new RuntimeException('Only StreamIO is supported');
        }

        // Note that this may be an issue for TLS connections: https://bugs.php.net/bug.php?id=70939
        $socket = socket_import_stream($io->getSocket());

        if (!$socket instanceof Socket) {
            throw new RuntimeException('Failed importing socket from stream');
        }

        try {
            $this->socketSubsystem->setSocketReadTimeout($socket, $seconds);
        } catch (SocketConfigurationFailedException $exception) {
            throw new TransportConfigurationFailedException(
                message: 'Could not set socket read timeout',
                previous: $exception
            );
        }

        // Work around the fact that, unfortunately, there is no public API
        // for dynamically modifying the read timeout.
        Closure::bind(function () use ($seconds) {
            $this->read_timeout = $seconds;
        }, $io, StreamIO::class)();
    }
}
