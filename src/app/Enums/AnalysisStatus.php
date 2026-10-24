<?php

namespace App\Enums;

enum AnalysisStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'En cola',
            self::Running => 'En ejecución',
            self::Completed => 'Completado',
            self::Failed => 'Fallido',
            self::Cancelled => 'Cancelado',
        };
    }
}
