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
use Asmblah\PhpAmqpCompat\Driver\Common\Exception\ExceptionHandlerInterface;
use Asmblah\PhpAmqpCompat\Logger\LoggerInterface;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;
use Exception;
use Mockery;
use Mockery\MockInterface;
use PhpAmqpLib\Channel\AMQPChannel as AmqplibChannel;
use PhpAmqpLib\Connection\AbstractConnection as AmqplibConnection;
use PhpAmqpLib\Exception\AMQPProtocolChannelException;
use PhpAmqpLib\Message\AMQPMessage as AmqplibMessage;
use PhpAmqpLib\Wire\AMQPTable as AmqplibTable;

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
    private MockInterface&ExceptionHandlerInterface $exceptionHandler;
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
        $this->exceptionHandler = mock(ExceptionHandlerInterface::class);
        $this->logger = mock(LoggerInterface::class, [
            'debug' => null,
        ]);
        $this->channelBridge = mock(AmqpChannelBridgeInterface::class, [
            'getAmqplibChannel' => $this->amqplibChannel,
            'getExceptionHandler' => $this->exceptionHandler,
            'getLogger' => $this->logger,
        ]);
        AmqpBridge::bridgeChannel($this->amqpChannel, $this->channelBridge);

        $this->exceptionHandler->allows('handleException')
            ->andReturnUsing(function (Exception $libraryException, string $exceptionClass, string $methodName) {
                throw new Exception(sprintf(
                    'handleException() :: %s() :: Library Exception<%s> -> %s :: message(%s)',
                    $methodName,
                    $libraryException::class,
                    $exceptionClass,
                    $libraryException->getMessage()
                ));
            })
            ->byDefault();

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
            ->exchange_bind(
                $exchangeName,
                $sourceExchangeName,
                $routingKey,
                $flags & AMQP_NOWAIT,
                Mockery::type(AmqplibTable::class)
            );

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

    public function testBindLogsSuccessAsDebug(): void
    {
        $this->amqpExchange->setFlags(AMQP_NOPARAM);
        $this->amqpExchange->setName('my_exchange');
        $this->amqplibChannel->allows()
            ->exchange_bind(
                'my_exchange',
                'your_exchange',
                'my_routing_key',
                false,
                Mockery::type(AmqplibTable::class)
            );

        $this->logger->expects()
            ->debug('AMQPExchange::bind(): Exchange bound')
            ->once();

        $this->amqpExchange->bind('your_exchange', 'my_routing_key', []);
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
            ->exchange_bind(
                $exchangeName,
                $sourceExchangeName,
                $routingKey,
                $flags & AMQP_NOWAIT,
                Mockery::type(AmqplibTable::class)
            )
            ->once()
            ->andReturnUsing(function ($_1, $_2, $_3, $_4, AmqplibTable $table) use ($arguments) {
                static::assertEquals($arguments, $table->getNativeData());
            });

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
            ->exchange_bind(
                $exchangeName,
                $sourceExchangeName,
                $routingKey,
                $flags & AMQP_NOWAIT,
                Mockery::type(AmqplibTable::class)
            )
            ->andThrow($exception);

        $this->expectExceptionMessage(
            'handleException() :: AMQPExchange::bind() :: ' .
            'Library Exception<PhpAmqpLib\Exception\AMQPProtocolChannelException> -> AMQPExchangeException :: ' .
            'message(my text)'
        );

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
                Mockery::type(AmqplibTable::class)
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

    public function testDeclareExchangeLogsSuccessAsDebug(): void
    {
        $this->amqpExchange->setName('my_exchange');
        $this->amqpExchange->setType(AMQP_EX_TYPE_FANOUT);
        $this->amqplibChannel->allows()
            ->exchange_declare(
                'my_exchange',
                AMQP_EX_TYPE_FANOUT,
                false,
                false,
                false,
                false,
                false,
                Mockery::type(AmqplibTable::class)
            );

        $this->logger->expects()
            ->debug('AMQPExchange::declareExchange(): Exchange declared')
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
                Mockery::type(AmqplibTable::class)
            )
            ->once()
            ->andReturnUsing(function ($_1, $_2, $_3, $_4, $_5, $_6, $_7, AmqplibTable $table) use ($arguments) {
                static::assertEquals($arguments, $table->getNativeData());
            });

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
                Mockery::type(AmqplibTable::class)
            )
            ->andThrow($exception);

        $this->expectExceptionMessage(
            'handleException() :: AMQPExchange::declareExchange() :: ' .
            'Library Exception<PhpAmqpLib\Exception\AMQPProtocolChannelException> -> AMQPExchangeException :: ' .
            'message(my text)'
        );

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

    public function testDeleteLogsSuccessAsDebug(): void
    {
        $this->amqplibChannel->allows()
            ->exchange_delete(
                'my_exchange',
                false,
                false
            );

        $this->logger->expects()
            ->debug('AMQPExchange::delete(): Exchange deleted')
            ->once();

        $this->amqpExchange->delete('my_exchange', AMQP_NOPARAM);
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

        $this->expectExceptionMessage(
            'handleException() :: AMQPExchange::delete() :: ' .
            'Library Exception<PhpAmqpLib\Exception\AMQPProtocolChannelException> -> AMQPExchangeException :: ' .
            'message(my text)'
        );

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

    /**
     * @param array<string, mixed> $attributes
     * @dataProvider publishDataProvider
     */
    public function testPublishLogsAttemptAsDebug(
        string $exchangeName,
        int $flags,
        string $routingKey,
        string $message,
        array $attributes
    ): void {
        $this->amqpExchange->setName($exchangeName);
        $this->amqplibChannel->allows()
            ->basic_publish(
                Mockery::type(AmqplibMessage::class),
                $exchangeName,
                $routingKey,
                (bool) ($flags & AMQP_MANDATORY),
                (bool) ($flags & AMQP_IMMEDIATE)
            );

        $this->logger->expects()
            ->debug('AMQPExchange::publish(): Message publish attempt', [
                'attributes' => $attributes,
                'exchange_name' => $exchangeName,
                'flags' => $flags,
                'message' => $message,
                'routing_key' => $routingKey,
            ])
            ->once();

        $this->amqpExchange->publish($message, $routingKey, $flags, $attributes);
    }

    public function testPublishLogsSuccessAsDebug(): void
    {
        $this->amqpExchange->setName('my_exchange');
        $this->amqplibChannel->allows()
            ->basic_publish(
                Mockery::type(AmqplibMessage::class),
                'my_exchange',
                null,
                false,
                false
            );

        $this->logger->expects()
            ->debug('AMQPExchange::publish(): Message published')
            ->once();

        $this->amqpExchange->publish('my message');
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
            ->once()
            ->andReturnUsing(function (AmqplibMessage $amqplibMessage) {
                static::assertSame('my message', $amqplibMessage->getBody());
            });

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

    /**
     * @param array<string, mixed> $attributes
     * @dataProvider publishDataProvider
     */
    public function testPublishHandlesAmqplibExceptionCorrectly(
        string $exchangeName,
        int $flags,
        string $routingKey,
        string $message,
        array $attributes
    ): void {
        $this->amqpExchange->setName($exchangeName);
        $exception = new AMQPProtocolChannelException(21, 'your text', [1, 2, 3]);

        $this->amqplibChannel->allows()
            ->basic_publish(
                Mockery::type(AmqplibMessage::class),
                $exchangeName,
                $routingKey,
                (bool) ($flags & AMQP_MANDATORY),
                (bool) ($flags & AMQP_IMMEDIATE)
            )
            ->andThrow($exception);

        $this->expectExceptionMessage(
            'handleException() :: AMQPExchange::publish() :: ' .
            'Library Exception<PhpAmqpLib\Exception\AMQPProtocolChannelException> -> AMQPExchangeException :: ' .
            'message(your text)'
        );

        $this->amqpExchange->publish($message, $routingKey, $flags);
    }

    /**
     * @return array<array<mixed>>
     */
    public static function publishDataProvider(): array
    {
        return [
            [
                'my_exchange',
                AMQP_IMMEDIATE,
                'my_routing_key',
                'this is my first message',
                ['x-first' => 'one', 'x-second' => 'two'],
            ],
            [
                'my_exchange',
                AMQP_MANDATORY,
                'my_routing_key',
                'this is my second message',
                ['x-first' => 'I am 1', 'x-second' => 'I am 2'],
            ],
        ];
    }

    public function testSetNameRaisesExceptionWhenNameExceeds255Characters(): void
    {
        $this->expectException(AMQPExchangeException::class);
        $this->expectExceptionMessage('Invalid exchange name given, must be less than 255 characters long.');

        $this->amqpExchange->setName(str_repeat('x', 256));
    }

    // Note that this is unlike the error message suggests.
    public function testSetNameDoesNotRaiseExceptionWhenNameIsExactly255Characters(): void
    {
        $validLongName = str_repeat('x', 255);

        $this->amqpExchange->setName($validLongName);

        static::assertSame($validLongName, $this->amqpExchange->getName());
    }

    /**
     * @param array<string, scalar> $arguments
     * @dataProvider unbindDataProvider
     */
    public function testUnbindLogsAttemptAsDebug(
        string $exchangeName,
        string $sourceExchangeName,
        string $routingKey,
        int $flags,
        array $arguments
    ): void {
        $this->amqpExchange->setFlags($flags);
        $this->amqpExchange->setName($exchangeName);
        $this->amqplibChannel->allows()
            ->exchange_unbind(
                $exchangeName,
                $sourceExchangeName,
                $routingKey,
                $flags & AMQP_NOWAIT,
                Mockery::type(AmqplibTable::class)
            );

        $this->logger->expects()
            ->debug('AMQPExchange::unbind(): Exchange unbind attempt', [
                'arguments' => $arguments,
                'exchange_name' => $exchangeName,
                'flags' => $flags,
                'routing_key' => $routingKey,
                'source_exchange_name' => $sourceExchangeName,
            ])
            ->once();

        $this->amqpExchange->unbind($sourceExchangeName, $routingKey, $arguments);
    }

    /**
     * @param array<string, scalar> $arguments
     * @dataProvider unbindDataProvider
     */
    public function testUnbindGoesViaAmqplib(
        string $exchangeName,
        string $sourceExchangeName,
        string $routingKey,
        int $flags,
        array $arguments
    ): void {
        $this->amqpExchange->setFlags($flags);
        $this->amqpExchange->setName($exchangeName);

        $this->amqplibChannel->expects()
            ->exchange_unbind(
                $exchangeName,
                $sourceExchangeName,
                $routingKey,
                $flags & AMQP_NOWAIT,
                Mockery::type(AmqplibTable::class)
            )
            ->once()
            ->andReturnUsing(function ($_1, $_2, $_3, $_4, AmqplibTable $table) use ($arguments) {
                static::assertEquals($arguments, $table->getNativeData());
            });

        static::assertTrue($this->amqpExchange->unbind($sourceExchangeName, $routingKey, $arguments));
    }

    /**
     * @param array<string, scalar> $arguments
     * @dataProvider unbindDataProvider
     */
    public function testUnbindHandlesAmqplibExceptionCorrectly(
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
            ->exchange_unbind(
                $exchangeName,
                $sourceExchangeName,
                $routingKey,
                $flags & AMQP_NOWAIT,
                Mockery::type(AmqplibTable::class)
            )
            ->andThrow($exception);

        $this->expectExceptionMessage(
            'handleException() :: AMQPExchange::unbind() :: ' .
            'Library Exception<PhpAmqpLib\Exception\AMQPProtocolChannelException> -> AMQPExchangeException :: ' .
            'message(my text)'
        );

        $this->amqpExchange->unbind($sourceExchangeName, $routingKey, $arguments);
    }

    /**
     * @return array<array<mixed>>
     */
    public static function unbindDataProvider(): array
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

    public function testUnbindLogsSuccessAsDebug(): void
    {
        $this->amqpExchange->setName('your_exchange');
        $this->amqplibChannel->allows()
            ->exchange_unbind(
                'your_exchange',
                'my_exchange',
                'my_routing_key',
                false,
                Mockery::type(AmqplibTable::class)
            );

        $this->logger->expects()
            ->debug('AMQPExchange::unbind(): Exchange unbound')
            ->once();

        $this->amqpExchange->unbind('my_exchange', 'my_routing_key');
    }
}
