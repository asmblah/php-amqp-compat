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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\Amqp;

use AMQPChannel;
use AMQPChannelException;
use AMQPExchange;
use AMQPExchangeException;
use Asmblah\PhpAmqpCompat\Bridge\AmqpBridge;
use Asmblah\PhpAmqpCompat\Bridge\Channel\AmqpChannelBridgeInterface;
use Asmblah\PhpAmqpCompat\Logger\LoggerInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Mockery;
use Mockery\MockInterface;
use PhpAmqpLib\Channel\AMQPChannel as AmqplibChannel;
use PhpAmqpLib\Connection\AbstractConnection as AmqplibConnection;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use PhpAmqpLib\Message\AMQPMessage as AmqplibMessage;

/**
 * Class AMQPExchangeTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class AMQPExchangeTest extends AbstractTestCase
{
    private MockInterface&AMQPChannel $amqpChannel;
    private AMQPExchange $amqpExchange;
    private MockInterface&AmqplibChannel $amqplibChannel;
    private MockInterface&AmqplibConnection $amqplibConnection;
    private MockInterface&AmqpChannelBridgeInterface $channelBridge;
    private MockInterface&LoggerInterface $logger;

    public function setUp(): void
    {
        $this->amqpChannel = mock(AMQPChannel::class);
        $this->amqplibConnection = mock(AmqplibConnection::class, [
            'isConnected' => true,
        ]);
        $this->amqplibChannel = mock(AmqplibChannel::class, [
            'getConnection' => $this->amqplibConnection,
            'is_open' => true,
        ]);
        $this->logger = mock(LoggerInterface::class, [
            'debug' => null,
        ]);
        $this->channelBridge = mock(AmqpChannelBridgeInterface::class, [
            'getAmqplibChannel' => $this->amqplibChannel,
            'getLogger' => $this->logger,
        ]);
        AmqpBridge::bridgeChannel($this->amqpChannel, $this->channelBridge);

        $this->amqpExchange = new AMQPExchange($this->amqpChannel);
    }

    public function testConstructorNotBeingCalledIsHandledCorrectly(): void
    {
        $extendedAmqpExchange = new class extends AMQPExchange {
            public function __construct()
            {
                // Deliberately omit the call to the super constructor.
            }
        };

        $this->expectException(AMQPChannelException::class);
        $this->expectExceptionMessage('Could not declare exchange. Stale reference to the channel object.');

        $extendedAmqpExchange->declareExchange();
    }

    /**
     * @param array<string, scalar> $arguments
     * @dataProvider bindDataProvider
     */
    public function testBindLogsAttemptAsDebug(
        string $exchangeName,
        string $sourceExchangeName,
        string $routingKey,
        int $flags,
        array $arguments
    ): void {
        $this->amqpExchange->setFlags($flags);
        $this->amqpExchange->setName($exchangeName);
        $this->amqplibChannel->allows()
            ->exchange_bind($exchangeName, $sourceExchangeName, $routingKey, $flags & AMQP_NOWAIT, $arguments);

        $this->logger->expects()
            ->debug('AMQPExchange::bind(): Exchange bind attempt', [
                'arguments' => $arguments,
                'exchange_name' => $exchangeName,
                'flags' => $flags,
                'routing_key' => $routingKey,
                'source_exchange_name' => $sourceExchangeName,
            ])
            ->once();

        $this->amqpExchange->bind($sourceExchangeName, $routingKey, $arguments);
    }

    /**
     * @param array<string, scalar> $arguments
     * @dataProvider bindDataProvider
     */
    public function testBindGoesViaAmqplib(
        string $exchangeName,
        string $sourceExchangeName,
        string $routingKey,
        int $flags,
        array $arguments
    ): void {
        $this->amqpExchange->setFlags($flags);
        $this->amqpExchange->setName($exchangeName);

        $this->amqplibChannel->expects()
            ->exchange_bind($exchangeName, $sourceExchangeName, $routingKey, $flags & AMQP_NOWAIT, $arguments)
            ->once();

        static::assertTrue($this->amqpExchange->bind($sourceExchangeName, $routingKey, $arguments));
    }

    /**
     * @param array<string, scalar> $arguments
     * @dataProvider bindDataProvider
     */
    public function testBindHandlesAmqplibExceptionCorrectly(
        string $exchangeName,
        string $sourceExchangeName,
        string $routingKey,
        int $flags,
        array $arguments
    ): void {
        $this->amqpExchange->setFlags($flags);
        $this->amqpExchange->setName($exchangeName);
        $exception = new AMQPProtocolChannelException(21, 'my text', [1, 2, 3]);
        $this->amqplibChannel->allows()
            ->exchange_bind($exchangeName, $sourceExchangeName, $routingKey, $flags & AMQP_NOWAIT, $arguments)
            ->andThrow($exception);

        $this->expectException(AMQPExchangeException::class);
        $this->expectExceptionMessage('AMQPExchange::bind(): Amqplib failure: my text');
        $this->logger->expects()
            ->logAmqplibException('AMQPExchange::bind', $exception)
            ->once();

        $this->amqpExchange->bind($sourceExchangeName, $routingKey, $arguments);
    }

    /**
     * @return array<array<mixed>>
     */
    public static function bindDataProvider(): array
    {
        return [
            [
                'my_exchange',
                'your_exchange',
                'my_routing_key',
                AMQP_NOWAIT,
                ['x-first' => 'one', 'x-second' => 'two'],
            ],
            [
                'exchange_a',
                'exchange_b',
                'their_routing_key',
                AMQP_NOPARAM,
                ['x-first' => 'eins', 'x-second' => 'zwei'],
            ],
        ];
    }

    /**
     * @param array<string, scalar> $arguments
     * @dataProvider declareExchangeDataProvider
     */
    public function testDeclareExchangeLogsAttemptAsDebug(
        string $exchangeName,
        string $exchangeType,
        int $flags,
        array $arguments
    ): void {
        $this->amqpExchange->setArguments($arguments);
        $this->amqpExchange->setFlags($flags);
        $this->amqpExchange->setName($exchangeName);
        $this->amqpExchange->setType($exchangeType);
        $this->amqplibChannel->allows()
            ->exchange_declare(
                $exchangeName,
                $exchangeType,
                $flags & AMQP_PASSIVE,
                $flags & AMQP_DURABLE,
                $flags & AMQP_AUTODELETE,
                $flags & AMQP_INTERNAL,
                $flags & AMQP_NOWAIT,
                $arguments
            );

        $this->logger->expects()
            ->debug('AMQPExchange::declareExchange(): Exchange declaration attempt', [
                'arguments' => $arguments,
                'exchange_name' => $exchangeName,
                'exchange_type' => $exchangeType,
                'flags' => $flags,
            ])
            ->once();

        $this->amqpExchange->declareExchange();
    }

    /**
     * @param array<string, scalar> $arguments
     * @dataProvider declareExchangeDataProvider
     */
    public function testDeclareExchangeDeclaresViaAmqplib(
        string $exchangeName,
        string $exchangeType,
        int $flags,
        array $arguments
    ): void {
        $this->amqpExchange->setArguments($arguments);
        $this->amqpExchange->setFlags($flags);
        $this->amqpExchange->setName($exchangeName);
        $this->amqpExchange->setType($exchangeType);

        $this->amqplibChannel->expects()
            ->exchange_declare(
                $exchangeName,
                $exchangeType,
                $flags & AMQP_PASSIVE,
                $flags & AMQP_DURABLE,
                $flags & AMQP_AUTODELETE,
                $flags & AMQP_INTERNAL,
                $flags & AMQP_NOWAIT,
                $arguments
            )
            ->once();

        $this->amqpExchange->declareExchange();
    }

    /**
     * @param array<string, scalar> $arguments
     * @dataProvider declareExchangeDataProvider
     */
    public function testDeclareExchangeHandlesAmqplibExceptionCorrectly(
        string $exchangeName,
        string $exchangeType,
        int $flags,
        array $arguments
    ): void {
        $this->amqpExchange->setArguments($arguments);
        $this->amqpExchange->setFlags($flags);
        $this->amqpExchange->setName($exchangeName);
        $this->amqpExchange->setType($exchangeType);
        $exception = new AMQPProtocolChannelException(21, 'my text', [1, 2, 3]);

        $this->amqplibChannel->allows()
            ->exchange_declare(
                $exchangeName,
                $exchangeType,
                $flags & AMQP_PASSIVE,
                $flags & AMQP_DURABLE,
                $flags & AMQP_AUTODELETE,
                $flags & AMQP_INTERNAL,
                $flags & AMQP_NOWAIT,
                $arguments
            )
            ->andThrow($exception);

        $this->expectException(AMQPExchangeException::class);
        $this->expectExceptionMessage('Server channel error: 21, message: my text');
        $this->logger->expects()
            ->logAmqplibException('AMQPExchange::declareExchange', $exception)
            ->once();

        $this->amqpExchange->declareExchange();
    }

    /**
     * @return array<array<mixed>>
     */
    public static function declareExchangeDataProvider(): array
    {
        return [
            [
                'my_exchange',
                AMQP_EX_TYPE_FANOUT,
                AMQP_PASSIVE & AMQP_DURABLE,
                ['x-first' => 'one', 'x-second' => 'two'],
            ],
            [
                'your_exchange',
                AMQP_EX_TYPE_TOPIC,
                AMQP_INTERNAL,
                ['x-first' => 'eins', 'x-second' => 'zwei'],
            ],
        ];
    }

    /**
     * @dataProvider deleteExchangeDataProvider
     */
    public function testDeleteLogsAttemptAsDebugWhenExchangeNameGivenAsArgument(
        string $exchangeName,
        int $flags
    ): void {
        $this->amqplibChannel->allows()
            ->exchange_delete(
                $exchangeName,
                (bool) ($flags & AMQP_IFUNUSED),
                (bool) ($flags & AMQP_NOWAIT)
            );

        $this->logger->expects()
            ->debug('AMQPExchange::delete(): Exchange deletion attempt', [
                'exchange_name' => $exchangeName,
                'flags' => $flags,
            ])
            ->once();

        $this->amqpExchange->delete($exchangeName, $flags);
    }

    /**
     * @dataProvider deleteExchangeDataProvider
     */
    public function testDeleteLogsAttemptAsDebugWhenExchangeNameSetOnInstanceAndArgIsNull(
        string $exchangeName,
        int $flags
    ): void {
        $this->amqplibChannel->allows()
            ->exchange_delete(
                $exchangeName,
                (bool) ($flags & AMQP_IFUNUSED),
                (bool) ($flags & AMQP_NOWAIT)
            );
        $this->amqpExchange->setName($exchangeName);

        $this->logger->expects()
            ->debug('AMQPExchange::delete(): Exchange deletion attempt', [
                'exchange_name' => $exchangeName,
                'flags' => $flags,
            ])
            ->once();

        $this->amqpExchange->delete(null, $flags); // @phpstan-ignore-line
    }

    /**
     * @dataProvider deleteExchangeDataProvider
     */
    public function testDeleteLogsAttemptAsDebugWhenExchangeNameSetOnInstanceAndArgIsEmptyString(
        string $exchangeName,
        int $flags
    ): void {
        $this->amqplibChannel->allows()
            ->exchange_delete(
                $exchangeName,
                (bool) ($flags & AMQP_IFUNUSED),
                (bool) ($flags & AMQP_NOWAIT)
            );
        $this->amqpExchange->setName($exchangeName);

        $this->logger->expects()
            ->debug('AMQPExchange::delete(): Exchange deletion attempt', [
                'exchange_name' => $exchangeName,
                'flags' => $flags,
            ])
            ->once();

        $this->amqpExchange->delete('', $flags);
    }

    /**
     * @dataProvider deleteExchangeDataProvider
     */
    public function testDeleteDeletesViaAmqplib(
        string $exchangeName,
        int $flags
    ): void {
        $this->amqpExchange->setName($exchangeName);

        $this->amqplibChannel->expects()
            ->exchange_delete(
                $exchangeName,
                (bool) ($flags & AMQP_IFUNUSED),
                (bool) ($flags & AMQP_NOWAIT)
            )
            ->once();

        $this->amqpExchange->delete($exchangeName, $flags);
    }

    /**
     * @dataProvider deleteExchangeDataProvider
     */
    public function testDeleteHandlesAmqplibExceptionCorrectly(
        string $exchangeName,
        int $flags
    ): void {
        $this->amqpExchange->setFlags($flags);
        $this->amqpExchange->setName($exchangeName);
        $exception = new AMQPProtocolChannelException(21, 'my text', [1, 2, 3]);

        $this->amqplibChannel->allows()
            ->exchange_delete(
                $exchangeName,
                (bool) ($flags & AMQP_IFUNUSED),
                (bool) ($flags & AMQP_NOWAIT)
            )
            ->andThrow($exception);

        $this->expectException(AMQPExchangeException::class);
        $this->expectExceptionMessage('Server channel error: 21, message: my text');
        $this->logger->expects()
            ->logAmqplibException('AMQPExchange::delete', $exception)
            ->once();

        $this->amqpExchange->delete($exchangeName, $flags);
    }

    /**
     * @return array<array<mixed>>
     */
    public static function deleteExchangeDataProvider(): array
    {
        return [
            'AMQP_NOPARAM' => [
                'my_exchange',
                AMQP_NOPARAM,
            ],
            'AMQP_IFUNUSED' => [
                'my_exchange',
                AMQP_IFUNUSED,
            ],
            'AMQP_NOWAIT' => [
                'my_exchange',
                AMQP_NOWAIT,
            ],
            'AMQP_IFUNUSED | AMQP_NOWAIT' => [
                'my_exchange',
                AMQP_IFUNUSED | AMQP_NOWAIT,
            ],
        ];
    }

    public function testPublishPublishesViaAmqplibWhenGivenMessageOnly(): void
    {
        $this->amqpExchange->setName('my_exchange');
        $this->amqplibChannel->expects()
            ->basic_publish(
                Mockery::type(AmqplibMessage::class),
                'my_exchange',
                null,
                false,
                false
            )
            ->once();

        $this->amqpExchange->publish('my message');
    }

    public function testPublishSetsDefaultContentTypeIfNeeded(): void
    {
        $this->amqpExchange->setName('my_exchange');
        $this->amqplibChannel->expects()
            ->basic_publish(Mockery::andAnyOtherArgs())
            ->once()
            ->andReturnUsing(function (AmqplibMessage $amqpMessage) {
                static::assertSame('text/plain', $amqpMessage->get_properties()['content_type']);
            });

        $this->amqpExchange->publish('my message');
    }
}
