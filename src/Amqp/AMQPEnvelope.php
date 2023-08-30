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
     * TODO: Keep constructor empty as per reference implementation
     *       and move to static internal factory method instead?
     */
    public function __construct(
        private readonly ?string $body = null,
        private readonly ?string $consumerTag = null,
        private readonly ?int $deliveryTag = null,
        private readonly ?string $exchangeName = null,
        private readonly ?bool $isRedelivery = null,
        private readonly ?string $routingKey = null,
        ?string $contentType = '',
        ?string $contentEncoding = '',
        array $headers = [],
        int $deliveryMode = AMQP_DELIVERY_MODE_TRANSIENT,
        int $priority = 0,
        ?string $correlationId = '',
        ?string $replyTo = '',
        ?string $expiration = '',
        ?string $messageId = '',
        ?int $timestamp = 0,
        ?string $type = '',
        ?string $userId = '',
        ?string $appId = '',
        ?string $clusterId = ''
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
    }

    /**
     * Fetches the body of the message, or false if none is set.
     */
    public function getBody(): string|false
    {
        // Note that ->getBody() is special and does not just return the underlying value,
        // unlike the other methods.
        if ($this->body === null) {
            return '';
        }

        if ($this->body === '') {
            return false;
        }

        return $this->body;
    }

    /**
     * Fetches the consumer tag of the message.
     */
    public function getConsumerTag(): ?string
    {
        return $this->consumerTag;
    }

    /**
     * Fetches the delivery tag of the message.
     */
    public function getDeliveryTag(): ?int
    {
        return $this->deliveryTag;
    }

    /**
     * Fetches the name of the exchange on which the message was published.
     */
    public function getExchangeName(): ?string
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
    public function getHeader(string $headerKey): string|false
    {
        return $this->hasHeader($headerKey) ?
            $this->headers[$headerKey] :
            false;
    }

    /**
     * Fetches the routing key of the message.
     */
    public function getRoutingKey(): ?string
    {
        return $this->routingKey;
    }

    /**
     * Determines whether the specified message header exists.
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
     */
    public function isRedelivery(): ?bool
    {
        return $this->isRedelivery;
    }
}
