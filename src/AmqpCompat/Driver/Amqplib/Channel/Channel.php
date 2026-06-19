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

namespace Asmblah\PhpAmqpCompat\Driver\Amqplib\Channel;

use AMQPEnvelope;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transformer\EnvelopeTransformerInterface;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transformer\MessageTransformerInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Channel\ChannelInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Exception\ExceptionHandlerInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Transport\TransportInterface;
use Exception;
use PhpAmqpLib\Channel\AMQPChannel as AmqplibChannel;
use PhpAmqpLib\Exception\AMQPExceptionInterface;
use PhpAmqpLib\Message\AMQPMessage as AmqplibMessage;
use PhpAmqpLib\Wire\AMQPTable as AmqplibTable;

/**
 * Class Channel.
 *
 * Provides the driver-level channel abstraction.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class Channel implements ChannelInterface
{
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly AmqplibChannel $amqplibChannel,
        private readonly ExceptionHandlerInterface $exceptionHandler,
        private readonly EnvelopeTransformerInterface $envelopeTransformer,
        private readonly MessageTransformerInterface $messageTransformer
    ) {
    }

    /**
     * @inheritDoc
     */
    public function basicAck(int $deliveryTag, bool $multiple, string $exceptionClass, string $methodName): void
    {
        try {
            $this->amqplibChannel->basic_ack($deliveryTag, $multiple);
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, $exceptionClass, $methodName);
        }
    }

    /**
     * @inheritDoc
     */
    public function basicCancel(
        string $consumerTag,
        bool $noWait,
        string $exceptionClass,
        string $methodName
    ): void {
        try {
            $this->amqplibChannel->basic_cancel($consumerTag, $noWait);
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, $exceptionClass, $methodName);
        }
    }

    /**
     * @inheritDoc
     */
    public function basicConsume(
        string $queueName,
        string $consumerTag,
        bool $noLocal,
        bool $autoAck,
        bool $exclusive,
        callable $callback,
        string $exceptionClass,
        string $methodName
    ): string {
        try {
            $consumerTag = $this->amqplibChannel->basic_consume(
                $queueName,
                $consumerTag,
                $noLocal,
                $autoAck, // A.K.A "no_ack".
                $exclusive,
                false, // Wait for the operation result.
                function (AmqplibMessage $message) use ($callback) {
                    // Transform the Amqplib-specific message into a standard AMQPEnvelope.
                    $amqpEnvelope = $this->envelopeTransformer->transformMessage($message);

                    $callback($amqpEnvelope);
                }
            );
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, $exceptionClass, $methodName);
        }

        return $consumerTag;
    }

    /**
     * @inheritDoc
     */
    public function basicGet(
        string $queueName,
        bool $autoAck,
        string $exceptionClass,
        string $methodName
    ): ?AMQPEnvelope {
        try {
            $amqplibMessage = $this->amqplibChannel->basic_get(
                $queueName,
                $autoAck // A.K.A "no_ack".
            );
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, $exceptionClass, $methodName);
        }

        if ($amqplibMessage === null) {
            return null; // No message available.
        }

        // Transform the Amqplib message into the required AMQPEnvelope.
        return $this->envelopeTransformer->transformMessage($amqplibMessage);
    }

    /**
     * @inheritDoc
     */
    public function basicNack(
        int $deliveryTag,
        bool $multiple,
        bool $requeue,
        string $exceptionClass,
        string $methodName
    ): void {
        try {
            $this->amqplibChannel->basic_nack($deliveryTag, $multiple, $requeue);
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, $exceptionClass, $methodName);
        }
    }

    /**
     * @inheritDoc
     */
    public function basicPublish(
        string $message,
        array $attributes,
        string $exchangeName,
        string $routingKey,
        bool $mandatory,
        bool $immediate,
        string $exceptionClass,
        string $methodName
    ): void {
        $amqplibMessage = $this->messageTransformer->transformEnvelope($message, $attributes);

        try {
            $this->amqplibChannel->basic_publish(
                $amqplibMessage,
                $exchangeName,
                $routingKey,
                $mandatory,
                $immediate
            );
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, $exceptionClass, $methodName);
        }
    }

    /**
     * @inheritDoc
     */
    public function basicQos(
        int $size,
        int $count,
        bool $global,
        string $exceptionClass,
        string $methodName
    ): void {
        try {
            $this->amqplibChannel->basic_qos($size, $count, $global);
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, $exceptionClass, $methodName);
        }
    }

    /**
     * @inheritDoc
     */
    public function basicRecover(bool $requeue, string $exceptionClass, string $methodName): void
    {
        try {
            $this->amqplibChannel->basic_recover($requeue);
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, $exceptionClass, $methodName);
        }
    }

    /**
     * @inheritDoc
     */
    public function basicReject(
        int $deliveryTag,
        bool $requeue,
        string $exceptionClass,
        string $methodName
    ): void {
        try {
            // Note from reference implementation: `basic.reject` is asynchronous,
            // and thus will not indicate failure if something goes wrong on the broker.
            $this->amqplibChannel->basic_reject($deliveryTag, $requeue);
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, $exceptionClass, $methodName);
        }
    }

    /**
     * @inheritDoc
     */
    public function bindExchange(
        string $destinationExchangeName,
        string $sourceExchangeName,
        string $routingKey,
        bool $noWait,
        array $arguments,
        string $exceptionClass,
        string $methodName
    ): void {
        try {
            $this->amqplibChannel->exchange_bind(
                $destinationExchangeName,
                $sourceExchangeName,
                $routingKey,
                $noWait,
                new AmqplibTable($arguments)
            );
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, $exceptionClass, $methodName);
        }
    }

    /**
     * @inheritDoc
     */
    public function bindQueue(
        string $queueName,
        string $exchangeName,
        string $routingKey,
        array $arguments,
        string $exceptionClass,
        string $methodName
    ): void {
        try {
            $this->amqplibChannel->queue_bind(
                $queueName,
                $exchangeName,
                $routingKey,
                false,
                new AmqplibTable($arguments)
            );
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, $exceptionClass, $methodName);
        }
    }

    /**
     * @inheritDoc
     */
    public function closeQuietly(): void
    {
        try {
            // Match the behaviour of php-amqp/ext-amqp: on destruction, close the channel if needed.
            $this->amqplibChannel->closeIfDisconnected();

            if ($this->amqplibChannel->is_open()) {
                $this->amqplibChannel->close();
            }
        } catch (AMQPExceptionInterface) {
        }
    }

    /**
     * @inheritDoc
     */
    public function commitTransaction(string $exceptionClass, string $methodName): void
    {
        try {
            $this->amqplibChannel->tx_commit();
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, $exceptionClass, $methodName);
        }
    }

    /**
     * @inheritDoc
     */
    public function declareExchange(
        string $exchangeName,
        string $exchangeType,
        bool $passive,
        bool $durable,
        bool $autoDelete,
        bool $internal,
        bool $noWait,
        array $arguments,
        string $exceptionClass,
        string $methodName
    ): void {
        try {
            $this->amqplibChannel->exchange_declare(
                $exchangeName,
                $exchangeType,
                $passive,
                $durable,
                $autoDelete,
                $internal,
                $noWait,
                new AmqplibTable($arguments)
            );
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, $exceptionClass, $methodName);
        }
    }

    /**
     * @inheritDoc
     */
    public function declareQueue(
        string $queueName,
        bool $passive,
        bool $durable,
        bool $exclusive,
        bool $autoDelete,
        bool $noWait,
        array $arguments,
        string $exceptionClass,
        string $methodName
    ): array {
        try {
            $result = $this->amqplibChannel->queue_declare(
                $queueName,
                $passive,
                $durable,
                $exclusive,
                $autoDelete,
                $noWait,
                new AmqplibTable($arguments)
            );
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, $exceptionClass, $methodName);
        }

        if (!is_array($result)) {
            throw new $exceptionClass($methodName . '(): Amqplib result was not an array');
        }

        // If the queue name was auto-generated, we need to extract it.
        $queueName = $result[0];

        if (count($result) < 2) {
            throw new $exceptionClass($methodName . '(): Amqplib result should contain message count at [1]');
        }

        $messageCount = (int) $result[1];

        return ['name' => $queueName, 'count' => $messageCount];
    }

    /**
     * @inheritDoc
     */
    public function deleteExchange(
        string $exchangeName,
        bool $ifUnused,
        bool $noWait,
        string $exceptionClass,
        string $methodName
    ): void {
        try {
            $this->amqplibChannel->exchange_delete($exchangeName, $ifUnused, $noWait);
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, $exceptionClass, $methodName);
        }
    }

    /**
     * @inheritDoc
     */
    public function deleteQueue(
        string $queueName,
        bool $ifUnused,
        bool $ifEmpty,
        bool $noWait,
        string $exceptionClass,
        string $methodName
    ): int {
        try {
            $result = $this->amqplibChannel->queue_delete(
                $queueName,
                $ifUnused,
                $ifEmpty,
                $noWait
            );
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, $exceptionClass, $methodName);
        }

        return (int) $result;
    }

    /**
     * @inheritDoc
     */
    public function getChannelId(): ?int
    {
        return $this->amqplibChannel->getChannelId();
    }

    /**
     * @inheritDoc
     */
    public function hasConnection(): bool
    {
        return $this->amqplibChannel->getConnection() !== null;
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
    public function isOpen(): bool
    {
        return $this->amqplibChannel->is_open();
    }

    /**
     * @inheritDoc
     */
    public function purgeQueue(
        string $queueName,
        bool $noWait,
        string $exceptionClass,
        string $methodName
    ): void {
        try {
            $this->amqplibChannel->queue_purge($queueName, $noWait);
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, $exceptionClass, $methodName);
        }
    }

    /**
     * @inheritDoc
     */
    public function rollbackTransaction(string $exceptionClass, string $methodName): void
    {
        try {
            $this->amqplibChannel->tx_rollback();
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, $exceptionClass, $methodName);
        }
    }

    /**
     * @inheritDoc
     */
    public function startTransaction(string $exceptionClass, string $methodName): void
    {
        try {
            $this->amqplibChannel->tx_select();
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, $exceptionClass, $methodName);
        }
    }

    /**
     * @inheritDoc
     */
    public function unbindExchange(
        string $destinationExchangeName,
        string $sourceExchangeName,
        string $routingKey,
        bool $noWait,
        array $arguments,
        string $exceptionClass,
        string $methodName
    ): void {
        try {
            $this->amqplibChannel->exchange_unbind(
                $destinationExchangeName,
                $sourceExchangeName,
                $routingKey,
                $noWait,
                new AmqplibTable($arguments)
            );
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, $exceptionClass, $methodName);
        }
    }

    /**
     * @inheritDoc
     */
    public function wait(float|int $timeout, string $exceptionClass, string $methodName): void
    {
        try {
            /*
             * Amqplib's internal wait loop will allow async signals or tocks to still be fired,
             * so that heartbeats can still be handled in between messages.
             */
            $this->amqplibChannel->wait(timeout: $timeout);
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException(
                $exception,
                $exceptionClass,
                $methodName,
                isConsumption: true
            );
        }
    }
}
