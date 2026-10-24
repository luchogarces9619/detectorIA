<?php

namespace App\Enums;

enum ModelStatus: string
{
    case Draft = 'draft';
    case Candidate = 'candidate';
    case Active = 'active';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Candidate => 'Candidato',
            self::Active => 'Activo',
            self::Retired => 'Retirado',
        };
    }
}
