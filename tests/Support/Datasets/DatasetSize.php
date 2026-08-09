<?php

namespace Tests\Support\Datasets;

enum DatasetSize: string
{
    case TINY = 'tiny';
    case SMALL = 'small';
    case MEDIUM = 'medium';
    case LARGE = 'large';
    case RACE = 'race';

    public function products(): int
    {
        return match ($this) {
            self::TINY => 1,
            self::SMALL => 10,
            self::MEDIUM => 100,
            self::LARGE => 10_000,
            self::RACE => 2,
        };
    }
}
