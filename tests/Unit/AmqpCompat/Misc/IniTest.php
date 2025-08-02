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

namespace Asmblah\PhpAmqpCompat\Tests\Unit\AmqpCompat\Misc;

use Asmblah\PhpAmqpCompat\Misc\Ini;
use Asmblah\PhpAmqpCompat\Tests\AbstractTestCase;

/**
 * Class IniTest.
 *
 * @author Dan Phillimore <dan@ovms.co>
 */
class IniTest extends AbstractTestCase
{
    private Ini $ini;

    public function setUp(): void
    {
        $this->ini = new Ini();
    }

    public function testGetRawIniSettingFetchesValidOption(): void
    {
        $phpVersion = $this->ini->getRawIniSetting('cfg_file_path');

        static::assertSame(php_ini_loaded_file(), $phpVersion);
    }

    public function testGetRawIniSettingReturnsFalseForUndefinedOption(): void
    {
        static::assertFalse($this->ini->getRawIniSetting('non_existent_setting_12345'));
    }

    public function testGetRawIniSettingHandlesEmptyString(): void
    {
        static::assertFalse($this->ini->getRawIniSetting(''));
    }
}
