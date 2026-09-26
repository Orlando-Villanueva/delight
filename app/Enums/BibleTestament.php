<?php

namespace App\Enums;

enum BibleTestament: string
{
    case Old = 'old';

    case New = 'new';

    case Deuterocanonical = 'deuterocanonical';

    public function label(): string
    {
        return match ($this) {
            self::Old => 'Old',
            self::New => 'New',
            self::Deuterocanonical => 'Deuterocanonical',
        };
    }
}
