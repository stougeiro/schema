<?php declare(strict_types=1);

namespace Tests\Support;

use STDW\Schema\Schema;

final class TestableSchema extends Schema
{
    public static function production(): void
    {
        self::$production = true;
    }
}
