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

namespace Asmblah\PhpAmqpCompat\Misc;

/**
 * Interface IniInterface.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
interface IniInterface
{
    /**
     * Fetches the given unprocessed raw INI setting.
     *
     * Useful to allow stubbing.
     */
    public function getRawIniSetting(string $option): array|false|string;
}
