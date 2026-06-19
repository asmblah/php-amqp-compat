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

namespace Asmblah\PhpAmqpCompat\Bridge\Channel;

/**
 * Interface Envelope.
 *
 * Defines message envelope -related types.
 *
 * @phpstan-type EnvelopeAttributes array{
 *                                       app_id?: string,
 *                                       content_encoding?: string,
 *                                       content_type?: string,
 *                                       delivery_mode?: string,
 *                                       expiration?: string,
 *                                       headers?: array<mixed>,
 *                                       message_id?: string,
 *                                       priority?: string,
 *                                       reply_to?: string,
 *                                       timestamp?: string,
 *                                       type?: string,
 *                                       user_id?: string,
 *                                      }
 * @author Dan Phillimore <dan@ovms.co>
 */
interface Envelope
{
}
