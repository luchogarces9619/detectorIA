<?php

namespace App\Enums;

enum AnalysisConclusion: string
{
    case NotConclusive = 'not_conclusive';
    case NoSignals = 'no_signals';
    case ReviewSuggested = 'review_suggested';
    case MixedSignals = 'mixed_signals';

    public function label(): string
    {
        return match ($this) {
            self::NotConclusive => 'No concluyente',
            self::NoSignals => 'Sin señales relevantes',
            self::ReviewSuggested => 'Revisión humana sugerida',
            self::MixedSignals => 'Señales mixtas',
        };
    }
}
