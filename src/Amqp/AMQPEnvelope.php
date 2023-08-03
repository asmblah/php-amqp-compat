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

/**
 * Class AMQPEnvelope.
 *
 * Emulates AMQPEnvelope from pecl-amqp.
 *
 * @see {@link https://github.com/php-amqp/php-amqp/blob/v1.11.0/stubs/AMQPEnvelope.php}
 */
class AMQPEnvelope extends AMQPBasicProperties
{
    /**
     * @var string
     */
    private $body;
    /**
     * @var string
     */
    private $consumerTag;
    /**
     * @var int
     */
    private $deliveryTag;
    /**
     * @var string
     */
    private $exchangeName;
    /**
     * @var bool
     */
    private $isRedelivery;
    /**
     * @var string
     */
    private $routingKey;

    /**
     * TODO: Keep constructor empty as per reference implementation
     *       and move to static internal factory method instead?
     *
     * @param string $body
     * @param string $consumerTag
     * @param int $deliveryTag
     * @param string $exchangeName
     * @param bool $isRedelivery
     * @param string $routingKey
     * @param string $contentType
     * @param string $contentEncoding
     * @param array $headers
     * @param int $deliveryMode
     * @param int $priority
     * @param string $correlationId
     * @param string $replyTo
     * @param string $expiration
     * @param string $messageId
     * @param int $timestamp
     * @param string $type
     * @param string $userId
     * @param string $appId
     * @param string $clusterId
     */
    public function __construct(
        string $body,
        string $consumerTag,
        int $deliveryTag,
        string $exchangeName,
        bool $isRedelivery,
        string $routingKey,
        string $contentType = '',
        string $contentEncoding = '',
        array $headers = [],
        int $deliveryMode = AMQP_DELIVERY_MODE_TRANSIENT,
        int $priority = 0,
        string $correlationId = '',
        string $replyTo = '',
        string $expiration = '',
        string $messageId = '',
        int $timestamp = 0,
        string $type = '',
        string $userId = '',
        string $appId = '',
        string $clusterId = ''
    ) {
        parent::__construct(
            $contentType,
            $contentEncoding,
            $headers,
            $deliveryMode,
            $priority,
            $correlationId,
            $replyTo,
            $expiration,
            $messageId,
            $timestamp,
            $type,
            $userId,
            $appId,
            $clusterId
        );

        $this->body = $body;
        $this->consumerTag = $consumerTag;
        $this->deliveryTag = $deliveryTag;
        $this->exchangeName = $exchangeName;
        $this->isRedelivery = $isRedelivery;
        $this->routingKey = $routingKey;
    }

    /**
     * Fetches the body of the message.
     *
     * @return string The contents of the message body.
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Fetches the consumer tag of the message.
     *
     * @return string The consumer tag of the message.
     */
    public function getConsumerTag(): string
    {
        return $this->consumerTag;
    }

    /**
     * Fetches the delivery tag of the message.
     *
     * @return int The delivery tag of the message.
     */
    public function getDeliveryTag(): int
    {
        return $this->deliveryTag;
    }

    /**
     * Fetches the name of the exchange on which the message was published.
     *
     * @return string
     */
    public function getExchangeName(): string
    {
        return $this->exchangeName;
    }

    /**
     * Fetches a specific message header.
     *
     * @param string $headerKey Name of the header to get the value for.
     *
     * @return string|bool The contents of the specified header or FALSE
     *                     if not set.
     */
    public function getHeader(string $headerKey)
    {
        return $this->hasHeader($headerKey) ?
            $this->headers[$headerKey] :
            false;
    }

    /**
     * Fetches the routing key of the message.
     *
     * @return string
     */
    public function getRoutingKey(): string
    {
        return $this->routingKey;
    }

    /**
     * Determines whether the specified message header exists.
     *
     * @param string $headerKey Name of the header to check.
     *
     * @return bool
     */
    public function hasHeader(string $headerKey): bool
    {
        return array_key_exists($headerKey, $this->headers);
    }

    /**
     * Determines whether this is a redelivery of the message.
     *
     * If this message has been delivered and AMQPEnvelope::nack() was called,
     * the message will be put back on the queue to be redelivered,
     * at which point the message will always return TRUE when this method is called.
     *
     * @return bool TRUE if this is a redelivery, FALSE otherwise.
     */
    public function isRedelivery(): bool
    {
        return $this->isRedelivery;
    }
}
