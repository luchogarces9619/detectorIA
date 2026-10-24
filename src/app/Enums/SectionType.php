<?php

namespace App\Enums;

enum SectionType: string
{
    case Title = 'title';
    case Heading = 'heading';
    case Abstract = 'abstract';
    case Introduction = 'introduction';
    case Methods = 'methods';
    case Results = 'results';
    case Discussion = 'discussion';
    case Conclusion = 'conclusion';
    case References = 'references';
    case Appendix = 'appendix';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Title => 'Título',
            self::Heading => 'Encabezado',
            self::Abstract => 'Resumen',
            self::Introduction => 'Introducción',
            self::Methods => 'Metodología',
            self::Results => 'Resultados',
            self::Discussion => 'Discusión',
            self::Conclusion => 'Conclusiones',
            self::References => 'Referencias',
            self::Appendix => 'Anexos',
            self::Other => 'Otra sección',
        };
    }
}
