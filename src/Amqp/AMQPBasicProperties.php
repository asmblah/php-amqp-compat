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
 * Class AMQPBasicProperties.
 *
 * Emulates AMQPBasicProperties from pecl-amqp.
 *
 * @see {@link https://github.com/php-amqp/php-amqp/blob/v1.11.0/stubs/AMQPBasicProperties.php}
 */
class AMQPBasicProperties
{
    /**
     * @param string $contentType
     * @param string $contentEncoding
     * @param array<string, mixed> $headers
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
        private readonly string $contentType = '',
        private readonly string $contentEncoding = '',
        protected readonly array $headers = [],
        private readonly int $deliveryMode = AMQP_DELIVERY_MODE_TRANSIENT,
        private readonly int $priority = 0,
        private readonly string $correlationId = '',
        private readonly string $replyTo = '',
        private readonly string $expiration = '',
        private readonly string $messageId = '',
        private readonly int $timestamp = 0,
        private readonly string $type = '',
        private readonly string $userId = '',
        private readonly string $appId = '',
        private readonly string $clusterId = ''
    ) {
    }

    /**
     * Fetches the message's application ID.
     */
    public function getAppId(): string
    {
        return $this->appId;
    }

    /**
     * Fetches the message's cluster ID.
     */
    public function getClusterId(): string
    {
        return $this->clusterId;
    }

    /**
     * Fetches the message's content encoding.
     */
    public function getContentEncoding(): string
    {
        return $this->contentEncoding;
    }

    /**
     * Fetches the message's content type.
     */
    public function getContentType(): string
    {
        return $this->contentType;
    }

    /**
     * Fetches the message's correlation ID.
     */
    public function getCorrelationId(): string
    {
        return $this->correlationId;
    }

    /**
     * Fetches the message's delivery mode.
     */
    public function getDeliveryMode(): int
    {
        return $this->deliveryMode;
    }

    /**
     * Fetches the expiration of the message.
     */
    public function getExpiration(): string
    {
        return $this->expiration;
    }

    /**
     * Fetches the message's headers.
     *
     * @return array<string, mixed> An array of key value pairs associated with the message.
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Fetches the message ID of the message.
     */
    public function getMessageId(): string
    {
        return $this->messageId;
    }

    /**
     * Fetches the message's priority.
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Fetches the message's reply-to address.
     */
    public function getReplyTo(): string
    {
        return $this->replyTo;
    }

    /**
     * Fetches the message's timestamp.
     */
    public function getTimestamp(): int
    {
        return $this->timestamp;
    }

    /**
     * Fetches the message's type.
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Fetches the message's user ID.
     */
    public function getUserId(): string
    {
        return $this->userId;
    }
}
