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
    private string $appId;
    private string $clusterId;
    private string $contentEncoding;
    private string $contentType;
    private string $correlationId;
    private int $deliveryMode;
    private string $expiration;
    protected array $headers;
    private string $messageId;
    private int $priority;
    private string $replyTo;
    private int $timestamp;
    private string $type;
    private string $userId;

    public function __construct(
        string $contentType = "",
        string $contentEncoding = "",
        array $headers = [],
        int $deliveryMode = AMQP_DELIVERY_MODE_TRANSIENT,
        int $priority = 0,
        string $correlationId = "",
        string $replyTo = "",
        string $expiration = "",
        string $messageId = "",
        int $timestamp = 0,
        string $type = "",
        string $userId = "",
        string $appId = "",
        string $clusterId = ""
    ) {
        $this->appId = $appId;
        $this->clusterId = $clusterId;
        $this->contentType = $contentType;
        $this->contentEncoding = $contentEncoding;
        $this->correlationId = $correlationId;
        $this->deliveryMode = $deliveryMode;
        $this->expiration = $expiration;
        $this->headers = $headers;
        $this->messageId = $messageId;
        $this->priority = $priority;
        $this->replyTo = $replyTo;
        $this->timestamp = $timestamp;
        $this->type = $type;
        $this->userId = $userId;
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
     * @return array An array of key value pairs associated with the message.
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
     *
     * @return string The message user id.
     */
    public function getUserId(): string
    {
        return $this->userId;
    }
}
