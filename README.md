# PHP AMQP-Compat.

[![Build Status](https://github.com/asmblah/php-amqp-compat/workflows/CI/badge.svg)](https://github.com/asmblah/php-amqp-compat/actions?query=workflow%3ACI)

[php-amqp/ext-amqp][1] compatibility using [php-amqplib][2].

## Why?
`php-amqp`/`librabbitmq` does not fully support [AMQP heartbeats][4], they are only supported during [blocking calls into the extension](https://github.com/php-amqp/php-amqp/tree/v1.11.0#persistent-connection).
With `php-amqplib`, we're able to send heartbeats more regularly, using Unix System V signals.
This library provides its own signal-based heartbeat sender, using `pcntl_async_signals(...)`
to allow for more frequent heartbeat handling, based on the logic in [php-amqplib's sender implementation][3].

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

## Limitations
- Persistent connections are not and cannot be supported from userland.
- If existing logic is checking for the `amqp` extension via `extension_loaded('amqp')`,
  it will fail because this library does not define an extension.
  During functional testing, `extension_loaded(...)` is hooked using [PHP Code Shift][5]
  to allow running tests from the reference implementation [php-amqp/ext-amqp][1],
  see [ReferenceImplementationTest](tests/Functional/Reference/ReferenceImplementationTest.php).

## See also
- The original php-amqp extension that this compatibility layer replaces: [php-amqp/ext-amqp][1]
- `php-amqplib`, which this library uses under the hood: [php-amqplib][2]

[1]: https://github.com/php-amqp/php-amqp
[2]: https://github.com/php-amqplib/php-amqplib
[3]: https://github.com/php-amqplib/php-amqplib/blob/v3.5.4/PhpAmqpLib/Connection/Heartbeat/PCNTLHeartbeatSender.php
[4]: https://www.rabbitmq.com/heartbeats.html
[5]: https://github.com/asmblah/php-code-shift
