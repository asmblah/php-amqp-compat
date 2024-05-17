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

namespace Asmblah\PhpAmqpCompat\Connection;

use Asmblah\PhpAmqpCompat\Connection\Amqplib\ConnectionFactoryInterface;
use Asmblah\PhpAmqpCompat\Connection\Config\ConnectionConfigInterface;
use PhpAmqpLib\Connection\AbstractConnection as AmqplibConnection;
use PhpAmqpLib\Connection\AMQPConnectionConfig;

/**
 * Class Connector.
 *
 * Default implementation that connects using php-amqplib's AMQPStreamConnection.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class Connector implements ConnectorInterface
{
    public function __construct(
        private readonly ConnectionFactoryInterface $connectionFactory,
        private readonly float $unlimitedTimeout,
        private readonly string $locale = 'en_US'
    ) {
    }

    /**
     * @inheritDoc
     */
    public function connect(ConnectionConfigInterface $config): AmqplibConnection
    {
        $connectionTimeout = $this->coerceTimeout($config->getConnectionTimeout());
        $readTimeout = $this->coerceTimeout($config->getReadTimeout());
        $rpcTimeout = $config->getRpcTimeout(); // RPC timeout may be specified as zero for unlimited.
        $writeTimeout = $this->coerceTimeout($config->getWriteTimeout());

        /*
         * Php-amqplib only supports a single read/write timeout,
         * so use the minimum of the two
         * as in PhpAmqpLib\Connection\AMQPConnectionFactory::getReadWriteTimeout(...).
         */
        $readWriteTimeout = min($readTimeout, $writeTimeout);

        return $this->connectionFactory->connect(
            $config->getHost(),
            $config->getPort(),
            $config->getUser(),
            $config->getPassword(),
            $config->getVirtualHost(),
            false,
            AMQPConnectionConfig::AUTH_AMQPPLAIN,
            $this->locale,
            $connectionTimeout,
            $readWriteTimeout,
            false,
            $config->getHeartbeatInterval(),
            $rpcTimeout,
            $config->getMaxChannels(),
            $config->getMaxFrameSize()
        );
    }

    /**
     * Unlike php-amqp/ext-amqp, php-amqplib does not support 0 as unlimited timeout,
     * so we use a configurable value instead.
     */
    private function coerceTimeout(float $givenTimeout): float
    {
        return $givenTimeout === 0.0 ? $this->unlimitedTimeout : $givenTimeout;
    }
}
