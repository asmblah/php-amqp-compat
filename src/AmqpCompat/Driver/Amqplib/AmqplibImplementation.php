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

namespace Asmblah\PhpAmqpCompat\Driver\Amqplib;

use Asmblah\PhpAmqpCompat\Configuration\ConfigurationInterface;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Connection\ConnectionFactory;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Connection\ConnectionFactoryInterface;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Connection\Connector;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Exception\ExceptionHandler;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Heartbeat\HeartbeatTransmitter;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Logger\Logger;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Logger\LoggerInterface;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Processor\ValueProcessor;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Processor\ValueProcessorInterface;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transformer\EnvelopeTransformer;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transformer\MessageTransformer;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transport\TransportConnector;
use Asmblah\PhpAmqpCompat\Driver\Common\Exception\ExceptionHandlerInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Heartbeat\HeartbeatTransmitterInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Transport\TransportConnectorInterface;
use Asmblah\PhpAmqpCompat\Driver\ImplementationInterface;
use Asmblah\PhpAmqpCompat\Misc\Clock;
use Asmblah\PhpAmqpCompat\Misc\ClockInterface;
use Asmblah\PhpAmqpCompat\Socket\SocketSubsystem;
use Asmblah\PhpAmqpCompat\Socket\SocketSubsystemInterface;

/**
 * Class AmqplibImplementation.
 *
 * Defines the driver-specific API.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AmqplibImplementation implements ImplementationInterface
{
    private readonly ExceptionHandlerInterface $exceptionHandler;
    private readonly LoggerInterface $logger;
    private readonly TransportConnectorInterface $transportConnector;

    public function __construct(
        ConfigurationInterface $configuration,
        ClockInterface $clock = new Clock(),
        ConnectionFactoryInterface $connectionFactory = new ConnectionFactory(),
        private readonly HeartbeatTransmitterInterface $heartbeatTransmitter = new HeartbeatTransmitter(),
        SocketSubsystemInterface $socketSubsystem = new SocketSubsystem(),
        ValueProcessorInterface $valueProcessor = new ValueProcessor()
    ) {
        $this->logger = new Logger($configuration->getLogger());
        $this->exceptionHandler = new ExceptionHandler($this->logger);
        $this->transportConnector = new TransportConnector(
            new Connector(
                $connectionFactory,
                $configuration->getUnlimitedTimeout()
            ),
            $socketSubsystem,
            $clock,
            $this->exceptionHandler,
            $this->logger,
            new EnvelopeTransformer($valueProcessor),
            new MessageTransformer($valueProcessor)
        );
    }

    /**
     * @inheritDoc
     */
    public function getExceptionHandler(): ExceptionHandlerInterface
    {
        return $this->exceptionHandler;
    }

    /**
     * @inheritDoc
     */
    public function getHeartbeatTransmitter(): HeartbeatTransmitterInterface
    {
        return $this->heartbeatTransmitter;
    }

    /**
     * @inheritDoc
     */
    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @inheritDoc
     */
    public function getTransportConnector(): TransportConnectorInterface
    {
        return $this->transportConnector;
    }
}
