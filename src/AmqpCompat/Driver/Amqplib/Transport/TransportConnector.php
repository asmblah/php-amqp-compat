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

namespace Asmblah\PhpAmqpCompat\Driver\Amqplib\Transport;

use AMQPConnectionException;
use Asmblah\PhpAmqpCompat\Connection\Config\ConnectionConfigInterface;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Connection\ConnectorInterface;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Logger\LoggerInterface;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transformer\EnvelopeTransformerInterface;
use Asmblah\PhpAmqpCompat\Driver\Amqplib\Transformer\MessageTransformerInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Exception\ExceptionHandlerInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Transport\TransportConnectorInterface;
use Asmblah\PhpAmqpCompat\Driver\Common\Transport\TransportInterface;
use Asmblah\PhpAmqpCompat\Misc\ClockInterface;
use Asmblah\PhpAmqpCompat\Socket\SocketSubsystemInterface;
use PhpAmqpLib\Exception\AMQPExceptionInterface;
use PhpAmqpLib\Exception\AMQPIOException;

/**
 * Class TransportConnector.
 *
 * Connects via the underlying php-amqplib, encapsulating the connection in a Transport.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class TransportConnector implements TransportConnectorInterface
{
    public function __construct(
        private readonly ConnectorInterface $connector,
        private readonly SocketSubsystemInterface $socketSubsystem,
        private readonly ClockInterface $clock,
        private readonly ExceptionHandlerInterface $exceptionHandler,
        private readonly LoggerInterface $logger,
        private readonly EnvelopeTransformerInterface $envelopeTransformer,
        private readonly MessageTransformerInterface $messageTransformer
    ) {
    }

    /**
     * @inheritDoc
     */
    public function connect(ConnectionConfigInterface $config, string $methodName): TransportInterface
    {
        try {
            // Open the underlying connection to the AMQP broker via php-amqplib.
            $amqplibConnection = $this->connector->connect($config);
        } catch (AMQPExceptionInterface $exception) {
            // TODO: Handle errors identically to php-amqp.

            // Log details of the internal php-amqplib exception,
            // that cannot be included in the php-amqp/ext-amqp -compatible exception.
            $this->logger->logAmqplibException($methodName, $exception);

            if ($exception instanceof AMQPIOException) {
                $message = 'Socket error: could not connect to host.';
            } else {
                $message = 'Library error: connection closed unexpectedly - Potential login failure.';
            }

            throw new AMQPConnectionException($message);
        }

        return new Transport(
            $amqplibConnection,
            $this->socketSubsystem,
            $this->clock,
            $this->exceptionHandler,
            $this->envelopeTransformer,
            $this->messageTransformer
        );
    }
}
