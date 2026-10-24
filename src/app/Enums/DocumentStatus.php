<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Uploaded = 'uploaded';
    case Validating = 'validating';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'Cargado',
            self::Validating => 'Validando',
            self::Processing => 'Procesando',
            self::Ready => 'Listo',
            self::Failed => 'Con error',
            self::Deleted => 'Eliminado',
        };
    }
}
