<?php

namespace App\Enums;

enum SegmentKind: string
{
    case Paragraph = 'paragraph';
    case Heading = 'heading';
    case Caption = 'caption';
    case Table = 'table';
    case Reference = 'reference';
    case ListItem = 'list_item';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Paragraph => 'Párrafo',
            self::Heading => 'Encabezado',
            self::Caption => 'Leyenda',
            self::Table => 'Tabla',
            self::Reference => 'Referencia',
            self::ListItem => 'Elemento de lista',
            self::Other => 'Otro',
        };
    }
}
