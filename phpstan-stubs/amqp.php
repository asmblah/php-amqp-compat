<?php

/*
 * PHP AMQP-Compat - php-amqp/ext-amqp compatibility.
 * Copyright (c) Dan Phillimore (asmblah)
 * https://github.com/asmblah/php-amqp-compat/
 *
 * Released under the MIT license.
 * https://github.com/asmblah/php-amqp-compat/raw/main/MIT-LICENSE.txt
 */

/*
 * PHPStan stubs to override the bundled ext-amqp stubs (inside phpstan.phar, from jetbrains/phpstorm-stubs),
 * which omit return types on many methods (causing PHPStan to infer void) and declare some
 * parameter types incorrectly.
 *
 * Loaded via `stubFiles` in phpstan.neon.dist so that PHPStan's StubPhpDocProvider reads
 * the @return and @param PHPDoc tags here and uses them to override the type information
 * from the bundled PhpStorm stubs.
 *
 * Only methods that have incorrect type information in the bundled stubs are included here.
 * The file is intentionally not in the `paths` config so PHPStan does not analyse it for
 * errors, only reading PHPDocs from it.
 */

class AMQPChannel
{
    /**
     * @return bool
     */
    public function basicRecover(bool $requeue = true): bool {}

    /**
     * @return bool
     */
    public function commitTransaction(): bool {}

    /**
     * @return bool
     */
    public function qos(int $size, int $count, bool $global = false): bool {}

    /**
     * @return bool
     */
    public function rollbackTransaction(): bool {}

    /**
     * @return bool
     */
    public function setGlobalPrefetchCount(int $count): bool {}

    /**
     * @return bool
     */
    public function setGlobalPrefetchSize(int $size): bool {}

    /**
     * @return bool
     */
    public function setPrefetchCount(int $count): bool {}

    /**
     * @return bool
     */
    public function setPrefetchSize(int $size): bool {}

    /**
     * @return bool
     */
    public function startTransaction(): bool {}
}

class AMQPConnection
{
    /**
     * @return bool
     */
    public function connect(): bool {}

    /**
     * @return bool
     */
    public function disconnect(): bool {}

    /**
     * @param float $timeout
     * @return bool
     */
    public function setReadTimeout(float $timeout): bool {}

    /**
     * @param float $timeout
     * @return bool
     */
    public function setTimeout(float $timeout): bool {}
}

class AMQPExchange
{
    /**
     * @param array<string, scalar> $arguments
     * @return bool
     */
    public function bind(string $exchangeName, ?string $routingKey = '', array $arguments = []): bool {}

    /**
     * @return bool
     */
    public function declareExchange(): bool {}

    /**
     * @return bool
     */
    public function delete(?string $exchangeName = null, int $flags = AMQP_NOPARAM): bool {}

    /**
     * @param array<mixed> $headers
     * @return bool
     */
    public function publish(
        string $message,
        ?string $routingKey = null,
        int $flags = AMQP_NOPARAM,
        array $headers = []
    ): bool {}

    /**
     * @param array<string, scalar> $arguments
     * @return bool
     */
    public function unbind(string $exchangeName, ?string $routingKey = '', array $arguments = []): bool {}
}

class AMQPQueue
{
    /**
     * @return bool
     */
    public function ack(int $deliveryTag, int $flags = AMQP_NOPARAM): bool {}

    /**
     * @return bool
     */
    public function cancel(string $consumerTag = ''): bool {}

    /**
     * @return bool
     */
    public function nack(int $deliveryTag, int $flags = AMQP_NOPARAM): bool {}

    /**
     * @return bool
     */
    public function reject(int $deliveryTag, int $flags = AMQP_NOPARAM): bool {}
}
