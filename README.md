# PHP AMQP-Compat.

[![Build Status](https://github.com/asmblah/php-amqp-compat/workflows/CI/badge.svg)](https://github.com/asmblah/php-amqp-compat/actions?query=workflow%3ACI)

[php-amqp/ext-amqp][php-amqp/ext-amqp] compatibility using [php-amqplib][php-amqplib].

## Why?
`php-amqp`/`librabbitmq` does not fully support [AMQP heartbeats][AMQP heartbeats],
they are only supported during [blocking calls into the extension](https://github.com/php-amqp/php-amqp/tree/v1.11.0#persistent-connection).

### Heartbeat sender
With `php-amqplib`, we're able to send heartbeats more regularly, in multiple ways:

1. Using a [ReactPHP][ReactPHP] [EventLoop][ReactPHP EventLoop] with [Envoylope EventLoop][Envoylope EventLoop].
2. Using [UNIX System V signals] with [Envoylope ext-pcntl][Envoylope ext-pcntl]

See the usage instructions for the packages above for installation.

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
$ composer require asmblah/php-amqp-compat
```

Lastly, install the relevant [heartbeat sender](#heartbeat-sender), if required.

That should be all the changes required - this userland library is designed as a drop-in replacement.

## Limitations
- Persistent connections are not and cannot be supported from userland.
- If existing logic is checking for the `amqp` extension via `extension_loaded('amqp')`,
  it will fail because this library does not define an extension.
  During functional testing, `extension_loaded(...)` is hooked using [PHP Code Shift][PHP Code Shift]
  to allow running tests from the reference implementation [php-amqp/ext-amqp][php-amqp/ext-amqp],
  see [ReferenceImplementationTest](tests/Functional/Reference/ReferenceImplementationTest.php).

## See also
- The original php-amqp extension that this compatibility layer replaces: [php-amqp/ext-amqp][php-amqp/ext-amqp].
- `php-amqplib`, which this library uses under the hood: [php-amqplib][php-amqplib].

[AMQP heartbeats]: https://www.rabbitmq.com/heartbeats.html
[Envoylope]: https://github.com/envoylope
[Envoylope EventLoop]: https://github.com/envoylope/event-loop
[Envoylope ext-pcntl]: https://github.com/envoylope/pcntl
[php-amqp/ext-amqp]: https://github.com/php-amqp/php-amqp
[php-amqplib]: https://github.com/php-amqplib/php-amqplib
[PHP Code Shift]: https://github.com/asmblah/php-code-shift
[ReactPHP]: https://reactphp.org/
[ReactPHP EventLoop]: https://github.com/reactphp/event-loop
[UNIX System V signals]: https://tldp.org/LDP/Linux-Filesystem-Hierarchy/html/signals.html
