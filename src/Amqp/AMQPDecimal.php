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
 * Class AMQPDecimal.
 *
 * Emulates AMQPDecimal from pecl-amqp.
 *
 * @see {@link https://github.com/php-amqp/php-amqp/blob/v1.11.0/stubs/AMQPDecimal.php}
 */
final class AMQPDecimal
{
    public const EXPONENT_MIN = 0;
    public const EXPONENT_MAX = 255;
    public const SIGNIFICAND_MIN = 0;
    public const SIGNIFICAND_MAX = 4294967295;

    private readonly int $exponent;
    private readonly int $significand;

    /**
     * @param int $exponent
     * @param int $significand
     *
     * @throws AMQPValueException
     */
    public function __construct(int $exponent, int $significand)
    {
        if ($exponent < self::EXPONENT_MIN) {
            throw new AMQPValueException('Decimal exponent value must be unsigned.');
        }

        if ($exponent > self::EXPONENT_MAX) {
            throw new AMQPValueException(
                sprintf(
                    'Decimal exponent value must be less than %u.',
                    self::EXPONENT_MAX
                )
            );
        }

        if ($significand < self::SIGNIFICAND_MIN) {
            throw new AMQPValueException('Decimal significand value must be unsigned.');
        }

        if ($significand > self::SIGNIFICAND_MAX) {
            throw new AMQPValueException(
                sprintf(
                    'Decimal significand value must be less than %u.',
                    self::SIGNIFICAND_MAX
                )
            );
        }

        $this->exponent = $exponent;
        $this->significand = $significand;
    }

    /**
     * Fetches the exponent of the decimal number.
     *
     * @return int
     */
    public function getExponent(): int
    {
        return $this->exponent;
    }

    /**
     * Fetches the significand of the decimal number.
     *
     * @return int
     */
    public function getSignificand(): int
    {
        return $this->significand;
    }
}
