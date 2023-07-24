<?php

/*
 * PHP AMQP-Compat - php-amqp/ext-amqp compatibility.
 * Copyright (c) Dan Phillimore (asmblah)
 * https://github.com/asmblah/php-amqp-compat/
 *
 * Released under the MIT license.
 * https://github.com/asmblah/php-amqp-compat/raw/master/MIT-LICENSE.txt
 */

declare(strict_types=1);

/**
 * Class AMQPDecimal.
 *
 * Emulates AMQPDecimal from pecl-amqp.
 *
 * @see {@link https://github.com/php-amqp/php-amqp/blob/v1.11.0/stubs/AMQPDecimal.php}
 */
class AMQPDecimal
{
    const EXPONENT_MIN = 0;
    const EXPONENT_MAX = 255;
    const SIGNIFICAND_MIN = 0;
    const SIGNIFICAND_MAX = 4294967295;

    /**
     * @var int
     */
    private $exponent;
    /**
     * @var int
     */
    private $significand;

    /**
     * @param int $exponent
     * @param int $significand
     *
     * @throws AMQPValueException
     */
    public function __construct(int $exponent, int $significand)
    {
        // TODO: Throw exception(s) when applicable?

        $this->exponent = $exponent;
        $this->significand = $significand;
    }

    /**
     * @return int
     */
    public function getExponent(): int
    {
        return $this->exponent;
    }

    /**
     * @return int
     */
    public function getSignificand(): int
    {
        return $this->significand;
    }
}
