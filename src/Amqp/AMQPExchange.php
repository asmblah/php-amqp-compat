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

use Asmblah\PhpAmqpCompat\Bridge\AmqpBridge;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transformer\MessageTransformerInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Exception\ExceptionHandlerInterface;
use Asmblah\PhpAmqpCompat\Logger\LoggerInterface;
use PhpAmqpLib\Channel\AMQPChannel as AmqplibChannel;
use PhpAmqpLib\Exception\AMQPExceptionInterface;
use PhpAmqpLib\Wire\AMQPTable as AmqplibTable;

/**
 * Class AMQPExchange.
 *
 * Emulates AMQPExchange from pecl-amqp.
 *
 * @phpstan-import-type EnvelopeAttributes from MessageTransformerInterface
 * @see {@link https://github.com/php-amqp/php-amqp/blob/v1.11.0/stubs/AMQPExchange.php}
 */
class AMQPExchange
{
    private readonly AMQPChannel $amqpChannel;
    /**
     * Nullable because the implementation allows for extension,
     * where the parent constructor may not be called.
     */
    private ?AmqplibChannel $amqplibChannel = null;
    /**
     * @var array<string, scalar>
     */
    private array $arguments = [];
    private readonly ExceptionHandlerInterface $exceptionHandler;
    private string $exchangeName = '';
    private string $exchangeType = ''; // Must be set to one of the AMQP_EX__TYPE* constants.
    private int $flags = 0;
    private readonly LoggerInterface $logger;
    private readonly MessageTransformerInterface $messageTransformer;

    /**
     * @throws AMQPChannelException When channel is not connected.
     * @throws AMQPConnectionException If the connection to the broker was
     *                                 lost.
     */
    public function __construct(AMQPChannel $amqpChannel)
    {
        $this->amqpChannel = $amqpChannel;

        $channelBridge = AmqpBridge::getBridgeChannel($amqpChannel);
        $this->exceptionHandler = $channelBridge->getExceptionHandler();
        $this->logger = $channelBridge->getLogger();
        $this->messageTransformer = $channelBridge->getMessageTransformer();

        // Always set here in the constructor, however the API allows for the class to be extended
        // and so this parent constructor may not be called. See reference implementation tests.
        $this->amqplibChannel = $channelBridge->getAmqplibChannel();

        $this->checkChannelOrThrow('Could not create exchange.');
    }

    /**
     * Binds this exchange to another exchange using the specified routing key.
     *
     * @param string $exchangeName Name of the exchange to bind.
     * @param string|null $routingKey The routing key to use for binding.
     * @param array<string, scalar> $arguments Additional binding arguments.
     *
     * @throws AMQPChannelException When the channel is not open.
     * @throws AMQPConnectionException When the connection to the broker was lost.
     * @throws AMQPExchangeException On failure.
     */
    public function bind(string $exchangeName, ?string $routingKey = '', array $arguments = []): bool
    {
        $routingKey ??= '';
        $amqplibChannel = $this->checkChannelOrThrow('Could not bind to exchange.');

        $this->logger->debug(__METHOD__ . '(): Exchange bind attempt', [
            'arguments' => $arguments,
            'exchange_name' => $this->exchangeName,
            'flags' => $this->flags,
            'routing_key' => $routingKey,
            'source_exchange_name' => $exchangeName,
        ]);

        try {
            $amqplibChannel->exchange_bind(
                $this->exchangeName,
                $exchangeName,
                $routingKey,
                (bool) ($this->flags & AMQP_NOWAIT),
                new AmqplibTable($arguments)
            );
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, AMQPExchangeException::class, __METHOD__);
        }

        $this->logger->debug(__METHOD__ . '(): Exchange bound');

        return true;
    }

    /**
     * Ensures the channel is usable or bails out if not.
     *
     * @throws AMQPChannelException
     * @throws AMQPConnectionException
     */
    private function checkChannelOrThrow(string $error): AmqplibChannel
    {
        if ($this->amqplibChannel === null) {
            throw new AMQPChannelException($error . ' Stale reference to the channel object.');
        }

        if (!$this->amqplibChannel->is_open()) {
            throw new AMQPChannelException($error . ' No channel available.');
        }

        if ($this->amqplibChannel->getConnection() === null) {
            throw new AMQPChannelException($error . ' Stale reference to the connection object.');
        }

        if (!$this->amqplibChannel->getConnection()->isConnected()) {
            throw new AMQPConnectionException($error . 'No connection available.');
        }

        return $this->amqplibChannel;
    }

    /**
     * Declares a new exchange on the broker.
     *
     * @return boolean TRUE on success or FALSE on failure.
     *
     * @throws AMQPExchangeException   On failure.
     * @throws AMQPChannelException    If the channel is not open.
     * @throws AMQPConnectionException If the connection to the broker was lost.
     */
    public function declareExchange(): bool
    {
        $amqplibChannel = $this->checkChannelOrThrow('Could not declare exchange.');

        if ($this->exchangeName === '') {
            throw new AMQPExchangeException('Could not declare exchange. Exchanges must have a name.');
        }

        if ($this->exchangeType === '') {
            throw new AMQPExchangeException('Could not declare exchange. Exchanges must have a type.');
        }

        $this->logger->debug(__METHOD__ . '(): Exchange declaration attempt', [
            'arguments' => $this->arguments,
            'exchange_name' => $this->exchangeName,
            'exchange_type' => $this->exchangeType,
            'flags' => $this->flags,
        ]);

        try {
            $amqplibChannel->exchange_declare(
                $this->exchangeName,
                $this->exchangeType,
                (bool) ($this->flags & AMQP_PASSIVE),
                (bool) ($this->flags & AMQP_DURABLE),
                (bool) ($this->flags & AMQP_AUTODELETE),
                (bool) ($this->flags & AMQP_INTERNAL),
                (bool) ($this->flags & AMQP_NOWAIT),
                new AmqplibTable($this->arguments)
            );
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, AMQPExchangeException::class, __METHOD__);
        }

        $this->logger->debug(__METHOD__ . '(): Exchange declared');

        return true;
    }

    /**
     * Deletes the exchange from the broker.
     *
     * @param string  $exchangeName Optional name of exchange to delete.
     * @param integer $flags        Optionally AMQP_IFUNUSED can be specified
     *                              to indicate the exchange should not be
     *                              deleted until no clients are connected to
     *                              it.
     *
     * @return bool true on success or false on failure.
     *
     * @throws AMQPExchangeException   On failure.
     * @throws AMQPChannelException    If the channel is not open.
     * @throws AMQPConnectionException If the connection to the broker was lost.
     */
    public function delete(?string $exchangeName = null, int $flags = AMQP_NOPARAM): bool
    {
        $amqplibChannel = $this->checkChannelOrThrow('Could not delete exchange.');

        if ($exchangeName === null || $exchangeName === '') {
            $exchangeName = $this->exchangeName;
        }

        $this->logger->debug(__METHOD__ . '(): Exchange deletion attempt', [
            'exchange_name' => $exchangeName,
            'flags' => $flags,
        ]);

        try {
            $amqplibChannel->exchange_delete(
                $exchangeName,
                (bool) ($flags & AMQP_IFUNUSED),
                (bool) ($flags & AMQP_NOWAIT)
            );
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, AMQPExchangeException::class, __METHOD__);
        }

        $this->logger->debug(__METHOD__ . '(): Exchange deleted');

        return true;
    }

    /**
     * Fetches the argument associated with the given key.
     *
     * @param string $key The key to look up.
     *
     * @return scalar The string or integer value associated
     *                with the given key, or FALSE if the key
     *                is not set.
     */
    public function getArgument(string $key)
    {
        return $this->arguments[$key] ?? false;
    }

    /**
     * Fetches all arguments for this exchange.
     *
     * @return array<string, scalar> An array containing all the set key/value pairs.
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * Fetches the AMQPChannel object in use.
     *
     * @return AMQPChannel
     */
    public function getChannel(): AMQPChannel
    {
        return $this->amqpChannel;
    }

    /**
     * Fetches the AMQPConnection object in use.
     *
     * @return AMQPConnection
     */
    public function getConnection(): AMQPConnection
    {
        return $this->amqpChannel->getConnection();
    }

    /**
     * Fetches all the flags currently set on this exchange.
     *
     * @return int An integer bitmask of all the flags currently set on this
     *             exchange object.
     */
    public function getFlags(): int
    {
        return $this->flags;
    }

    /**
     * Fetches the configured name.
     *
     * @return string The configured name as a string.
     */
    public function getName(): string
    {
        return $this->exchangeName;
    }

    /**
     * Fetches the configured type.
     *
     * @return string The configured type as a string.
     */
    public function getType(): string
    {
        return $this->exchangeType;
    }

    /**
     * Determines whether an argument exists with the given key.
     *
     * @param string $key The key to look up.
     *
     * @return bool
     */
    public function hasArgument(string $key): bool
    {
        return array_key_exists($key, $this->arguments);
    }

    /**
     * Publishes a message to this exchange.
     *
     * @param string $message The message to publish.
     * @param string|null $routingKey The optional routing key to which to
     *                           publish to.
     * @param integer $flags One or more of AMQP_MANDATORY and
     *                       AMQP_IMMEDIATE.
     * @param EnvelopeAttributes $attributes
     *
     * @return bool TRUE on success or FALSE on failure.
     *
     * @throws AMQPChannelException If the channel is not open.
     * @throws AMQPConnectionException If the connection to the broker was lost.
     * @throws AMQPExchangeException On failure.
     */
    public function publish(
        string $message,
        ?string $routingKey = null,
        int $flags = AMQP_NOPARAM,
        array $attributes = []
    ): bool {
        $routingKey ??= '';
        $amqplibChannel = $this->checkChannelOrThrow('Could not publish to exchange.');

        $this->logger->debug(__METHOD__ . '(): Message publish attempt', [
            'attributes' => $attributes,
            'exchange_name' => $this->exchangeName,
            'flags' => $flags,
            'message' => $message,
            'routing_key' => $routingKey,
        ]);

        $amqplibMessage = $this->messageTransformer->transformEnvelope($message, $attributes);

        try {
            $amqplibChannel->basic_publish(
                $amqplibMessage,
                $this->exchangeName,
                $routingKey,
                (bool) ($flags & AMQP_MANDATORY),
                (bool) ($flags & AMQP_IMMEDIATE)
            );
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, AMQPExchangeException::class, __METHOD__);
        }

        $this->logger->debug(__METHOD__ . '(): Message published');

        return true;
    }

    /**
     * Sets an exchange argument.
     *
     * @throws AMQPExchangeException When an invalid value is given.
     */
    public function setArgument(string $key, mixed $value): bool
    {
        if ($value !== null && !is_int($value) && !is_float($value) && !is_string($value)) {
            throw new AMQPExchangeException(
                'The value parameter must be of type NULL, int, double or string.'
            );
        }

        if ($value === null) {
            // When null is given, it is treated specially: it represents that the argument is to be removed.
            unset($this->arguments[$key]);
        } else {
            $this->arguments[$key] = $value;
        }

        return true;
    }

    /**
     * Sets all arguments on the exchange.
     *
     * @param array<string, scalar> $arguments An array of key/value pairs of arguments.
     *
     * @return bool TRUE on success or FALSE on failure.
     */
    public function setArguments(array $arguments): bool
    {
        $this->arguments = $arguments;

        return true;
    }

    /**
     * Sets the flags on an exchange.
     *
     * @param integer|null $flags A bitmask of flags. This call currently only
     *                            considers the following flags:
     *                            AMQP_DURABLE, AMQP_PASSIVE
     *                            (and AMQP_DURABLE, if librabbitmq version >= 0.5.3)
     *
     * @return void
     */
    public function setFlags(?int $flags): void
    {
        $this->flags = $flags ?? 0;
    }

    /**
     * Sets the name of the exchange.
     *
     * @param string $exchangeName The name of the exchange to set as string.
     */
    public function setName(string $exchangeName): void
    {
        // This logic and message matches the reference implementation.
        if (strlen($exchangeName) > 255) {
            throw new AMQPExchangeException('Invalid exchange name given, must be less than 255 characters long.');
        }

        $this->exchangeName = $exchangeName;
    }

    /**
     * Sets the type of the exchange, which can be one of:
     *
     * - AMQP_EX_TYPE_DIRECT
     * - AMQP_EX_TYPE_FANOUT
     * - AMQP_EX_TYPE_HEADERS
     * - AMQP_EX_TYPE_TOPIC.
     *
     * @param string $exchangeType The type of exchange as a string.
     */
    public function setType(string $exchangeType): void
    {
        $this->exchangeType = $exchangeType;
    }

    /**
     * Unbinds this exchange from another exchange when using the specified routing key.
     *
     * @param array<string, scalar> $arguments
     *
     * @throws AMQPChannelException If the channel is not open.
     * @throws AMQPConnectionException If the connection to the broker was lost.
     * @throws AMQPExchangeException On failure.
     */
    public function unbind(string $exchangeName, ?string $routingKey = '', array $arguments = []): bool
    {
        $routingKey ??= '';
        $amqplibChannel = $this->checkChannelOrThrow('Could not unbind from exchange.');

        $this->logger->debug(__METHOD__ . '(): Exchange unbind attempt', [
            'arguments' => $arguments,
            'exchange_name' => $this->exchangeName,
            'flags' => $this->flags,
            'routing_key' => $routingKey,
            'source_exchange_name' => $exchangeName,
        ]);

        try {
            $amqplibChannel->exchange_unbind(
                $this->exchangeName,
                $exchangeName,
                $routingKey,
                (bool) ($this->flags & AMQP_NOWAIT),
                new AmqplibTable($arguments)
            );
        } catch (AMQPExceptionInterface $exception) {
            /** @var AMQPExceptionInterface&Exception $exception */
            $this->exceptionHandler->handleException($exception, AMQPExchangeException::class, __METHOD__);
        }

        $this->logger->debug(__METHOD__ . '(): Exchange unbound');

        return true;
    }
}
