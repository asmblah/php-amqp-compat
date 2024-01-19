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
    // Note these constants must be strings as per the reference implementation.
    public const MIN = '0';
    public const MAX = '18446744073709551616';
    /**
     * Use a string as per the reference implementation.
     */
    private ?string $timestamp = null;

    /**
     * @param float|int $timestamp Must be untyped for error emulation.
     *
     * @throws AMQPValueException
     */
    public function __construct(/*float */mixed $timestamp = 0.0)
    {
        // Manually implement argument count and type checking logic
        // so that the behaviour is identical to the reference implementation.
        if (func_num_args() < 1) {
            throw new ArgumentCountError('AMQPTimestamp::__construct() expects exactly 1 argument, 0 given');
        }

        if (is_int($timestamp)) {
            $timestamp = (float)$timestamp;
        } elseif (!is_float($timestamp)) {
            throw new TypeError(
                sprintf(
                    'AMQPTimestamp::__construct(): Argument #1 ($timestamp) must be of type float, %s given',
                    gettype($timestamp)
                )
            );
        }

        if ($timestamp < self::MIN) {
            throw new AMQPValueException(
                sprintf(
                    'The timestamp parameter must be greater than %0.0f.', self::MIN
                )
            );
        }

        // This logic and message matches the reference implementation.
        if ($timestamp > self::MAX) {
            throw new AMQPValueException(
                sprintf(
                    'The timestamp parameter must be less than %0.0f.',
                    self::MAX
                )
            );
        }

        $this->timestamp = (string) floor($timestamp);
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
        return $this->timestamp;
    }
}
