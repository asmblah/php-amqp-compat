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
 * Class AMQPTimestamp.
 *
 * Emulates AMQPTimestamp from pecl-amqp.
 *
 * @see {@link https://github.com/php-amqp/php-amqp/blob/v1.11.0/stubs/AMQPTimestamp.php}
 */
final class AMQPTimestamp
{
    const MIN = "0";
    const MAX = "18446744073709551616";
    /**
     * @var string
     */
    private $timestamp;

    /**
     * @param string $timestamp
     *
     * @throws AMQPValueException
     */
    public function __construct(string $timestamp)
    {
        // TODO: Add checks that throw AMQPValueException on failure (must be between MIN and MAX?).

        $this->timestamp = $timestamp;
    }

    /**
     * @return string
     */
    public function getTimestamp(): string
    {
        return $this->timestamp;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        throw new BadMethodCallException(__METHOD__ . ' not yet implemented');
    }
}
