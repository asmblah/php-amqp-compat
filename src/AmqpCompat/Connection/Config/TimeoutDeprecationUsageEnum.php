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

namespace Asmblah\PhpAmqpCompat\Connection\Config;

/**
 * Enum TimeoutDeprecationUsageEnum.
 *
 * Determines in what way (if any) the deprecated AMQP timeout setting was used.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
enum TimeoutDeprecationUsageEnum
{
    // Deprecated setting was not used at all.
    case NOT_USED;

    // Deprecated setting was used as well as its replacement at the same time.
    case SHADOWED;

    // Deprecated setting was used, its replacement was not.
    case USED_ALONE;
}
