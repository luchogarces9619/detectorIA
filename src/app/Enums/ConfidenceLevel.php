<?php

namespace App\Enums;

enum ConfidenceLevel: string
{
    case Insufficient = 'insufficient';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Insufficient => 'Evidencia insuficiente',
            self::Low => 'Confianza baja',
            self::Medium => 'Confianza media',
            self::High => 'Confianza alta',
        };
    }
}
