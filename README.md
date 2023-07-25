# PHP AMQP-Compat.

[![Build Status](https://github.com/asmblah/php-amqp-compat/workflows/CI/badge.svg)](https://github.com/asmblah/php-amqp-compat/actions?query=workflow%3ACI)

[EXPERIMENTAL] [php-amqp/ext-amqp]() compatibility using [php-amqplib]().

## Why?
`php-amqp`/`librabbitmq` does not fully support [AMQP heartbeats](), they are only supported during [blocking calls into the extension](https://github.com/php-amqp/php-amqp/tree/v1.11.0#persistent-connection).
With `php-amqplib`, we're able to send heartbeats more regularly, using Unix System V signals.
This library provides its own signal-based heartbeat sender, using `pcntl_async_signals(...)`
to allow for more frequent heartbeat handling, based on the logic in [php-amqplib's sender implementation]().

## Usage
First, remove `ext-amqp` - it cannot be used at the same time as this compatibility layer.
Usually, this will be with PECL:

```shell
$ pecl uninstall amqp
```

> This is because the classes installed in the global/root namespace such as `AMQPConnection`
> would conflict. 

Second, install this package with Composer:

```shell
$ composer install asmblah/php-amqp-compat
```

That should be all the changes required - this userland library is designed as a drop-in replacement.

## See also

- The original php-amqp extension that this compatibility layer replaces: [php-amqp/ext-amqp]()
- `php-amqplib`, which this library uses under the hood: [php-amqplib]()

[php-amqp/ext-amqp]: https://github.com/php-amqp/php-amqp
[php-amqplib]: https://github.com/php-amqplib/php-amqplib
[php-amqplib's sender implementation]: https://github.com/php-amqplib/php-amqplib/blob/v3.5.4/PhpAmqpLib/Connection/Heartbeat/PCNTLHeartbeatSender.php
[AMQP heartbeats]: https://www.rabbitmq.com/heartbeats.html
