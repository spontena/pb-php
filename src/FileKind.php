<?php

declare(strict_types=1);

namespace Spontena\PbPhp;

enum FileKind: string
{
    case File = 'file';
    case Map = 'map';
    case Set = 'set';
    case Substitution = 'substitution';
    case Pdefaults = 'pdefaults';
    case Properties = 'properties';

    public function hasFilenameInPath(): bool
    {
        return match ($this) {
            self::Pdefaults, self::Properties => false,
            default => true,
        };
    }

    public static function fromExtension(string $extension): ?self
    {
        return match (strtolower($extension)) {
            'aiml' => self::File,
            'set' => self::Set,
            'map' => self::Map,
            'substitution' => self::Substitution,
            'pdefaults' => self::Pdefaults,
            'properties' => self::Properties,
            default => null,
        };
    }
}
