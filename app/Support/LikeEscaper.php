<?php

declare(strict_types=1);

namespace App\Support;

final class LikeEscaper
{
    public static function escape(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    public static function contains(string $value): string
    {
        return '%'.self::escape($value).'%';
    }

    public static function prefix(string $value): string
    {
        return self::escape($value).'%';
    }
}
