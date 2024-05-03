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

namespace Asmblah\PhpAmqpCompat\Driver\Amqplib\Processor;

use AMQPDecimal;
use AMQPTimestamp;
use Asmblah\PhpAmqpCompat\Driver\Common\Processor\ValueProcessorInterface;
use DateTime;
use DateTimeInterface;
use PhpAmqpLib\Wire\AMQPDecimal as AmqplibDecimal;

/**
 * Class ValueProcessor.
 *
 * Transforms AMQP values either originating from or destined for the driver.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class ValueProcessor implements ValueProcessorInterface
{
    /**
     * @inheritDoc
     */
    public function processValueForDriver(mixed $value): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $elementValue) {
                $value[$key] = $this->processValueForDriver($elementValue);
            }

            return $value;
        }

        if ($value instanceof AMQPDecimal) {
            // Note that php-amqplib's significand and exponent parameters are reversed.
            return new AmqplibDecimal($value->getSignificand(), $value->getExponent());
        }

        if ($value instanceof AMQPTimestamp) {
            return DateTime::createFromFormat('U', $value->getTimestamp());
        }

        return $value;
    }

    /**
     * @inheritDoc
     */
    public function processValueFromDriver(mixed $value): mixed
    {
        if (is_array($value)) {
            foreach ($value as $key => $elementValue) {
                $value[$key] = $this->processValueFromDriver($elementValue);
            }

            return $value;
        }

        if ($value instanceof AmqplibDecimal) {
            // Note that php-amqplib's significand and exponent parameters are reversed.
            return new AMQPDecimal($value->getE(), $value->getN());
        }

        if ($value instanceof DateTimeInterface) {
            return new AMQPTimestamp($value->getTimestamp());
        }

        return $value;
    }
}
