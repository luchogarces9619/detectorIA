<?php

namespace App\Enums;

enum SegmentLabel: string
{
    case Human = 'human';
    case Ai = 'ai';
    case Mixed = 'mixed';
    case Uncertain = 'uncertain';

    public function label(): string
    {
        return match ($this) {
            self::Human => 'Sin señales relevantes',
            self::Ai => 'Requiere revisión',
            self::Mixed => 'Señales mixtas',
            self::Uncertain => 'No concluyente',
        };
    }
}
