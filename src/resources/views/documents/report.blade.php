<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Ficha de Análisis - {{ $document->original_name }}</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; line-height: 1.4; color: #1e293b; }
        .header { border-bottom: 3px solid #0ea5e9; padding-bottom: 10px; margin-bottom: 20px; }
        .title { font-size: 20px; font-weight: bold; color: #0f172a; }
        .subtitle { font-size: 11px; color: #475569; margin-top: 2px; }
        .status-badge { display: inline-block; padding: 4px 10px; background: #dcfce7; color: #166534; border-radius: 4px; font-weight: bold; margin-top: 6px; }
        .section { margin-bottom: 18px; }
        .section-title { font-size: 13px; font-weight: bold; color: #0f172a; margin-bottom: 8px; background: linear-gradient(to right, #f1f5f9, #ffffff); padding: 6px 8px; border-left: 3px solid #0ea5e9; }
        .grid { display: table; width: 100%; }
        .row { display: table-row; }
        .cell { display: table-cell; padding: 5px 8px; border: 1px solid #e2e8f0; vertical-align: top; }
        .cell-label { background: #f8fafc; font-weight: bold; width: 32%; }
        .highlight { background: #eff6ff; font-weight: bold; }
        .match-box { margin-top: 6px; padding: 8px; background: #fefce8; border: 1px solid #fde047; border-left: 4px solid #f59e0b; border-radius: 4px; }
        .match-header { font-size: 10px; color: #92400e; font-weight: bold; margin-bottom: 4px; }
        .match-text { font-size: 10px; color: #78350f; margin-bottom: 4px; line-height: 1.3; }
        .source-box { margin-top: 4px; padding: 4px 6px; background: #f0f9ff; border-left: 2px solid #0ea5e9; }
        .source-link { font-size: 9px; color: #0369a1; }
        .alert { padding: 8px; background: #eff6ff; border: 1px solid #93c5fd; border-radius: 4px; margin-top: 10px; font-size: 10px; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">SciTrace AI - Ficha de Análisis Completa</div>
        <div class="subtitle">Sistema de Detección de IA y Análisis de Similitud Textual</div>
        <div class="subtitle">Generado: {{ now()->format('d/m/Y H:i:s') }}</div>
        @if ($document->status === \App\Enums\DocumentStatus::Ready && isset($analysis) && isset($plagiarism))
            <span class="status-badge">✓ ANÁLISIS COMPLETADO</span>
        @elseif ($document->status === \App\Enums\DocumentStatus::Ready)
            <span class="status-badge" style="background: #fef3c7; color: #92400e;">⚠ ANÁLISIS PARCIAL</span>
        @else
            <span class="status-badge" style="background: #fee2e2; color: #991b1b;">✗ ANÁLISIS PENDIENTE</span>
        @endif
    </div>

    <div class="section">
        <div class="section-title">Información del Documento</div>
        <div class="grid">
            <div class="row">
                <div class="cell cell-label">Nombre</div>
                <div class="cell">{{ $document->original_name }}</div>
            </div>
            <div class="row">
                <div class="cell cell-label">Tamaño</div>
                <div class="cell">{{ number_format($document->size_bytes / 1024, 1, ',', '.') }} KB</div>
            </div>
            <div class="row">
                <div class="cell cell-label">Palabras</div>
                <div class="cell">{{ number_format($document->word_count, 0, ',', '.') }}</div>
            </div>
            <div class="row">
                <div class="cell cell-label">Estado</div>
                <div class="cell">{{ $document->status->label() }}</div>
            </div>
        </div>
    </div>

    @if ($analysis)
    <div class="section">
        <div class="section-title">📊 ANÁLISIS DE DETECCIÓN DE IA - Resultados Específicos</div>
        <div class="grid">
            <div class="row">
                <div class="cell cell-label">Conclusión del Análisis</div>
                <div class="cell highlight">
                    @if ($analysis['conclusion'] === 'not_conclusive') No concluyente
                    @elseif ($analysis['conclusion'] === 'no_signals') Sin señales relevantes de IA
                    @elseif ($analysis['conclusion'] === 'review_suggested') Revisión humana sugerida
                    @elseif ($analysis['conclusion'] === 'mixed_signals') Señales mixtas detectadas
                    @else {{ $analysis['conclusion'] }} @endif
                </div>
            </div>
            <div class="row">
                <div class="cell cell-label">Nivel de Confianza</div>
                <div class="cell">{{ $analysis['confidence'] === 'insufficient' ? 'Evidencia insuficiente para conclusión' : ucfirst($analysis['confidence']) }}</div>
            </div>
            <div class="row">
                <div class="cell cell-label">Puntuación Agregada</div>
                <div class="cell">{{ $analysis['aggregate_score'] !== null ? number_format($analysis['aggregate_score'], 4, ',', '.') : '—' }}</div>
            </div>
            <div class="row">
                <div class="cell cell-label">Cobertura Analizada</div>
                <div class="cell">{{ number_format(($analysis['coverage'] ?? 0) * 100, 1, ',', '.') }} % del documento</div>
            </div>
            <div class="row">
                <div class="cell cell-label">Fragmentos Decididos</div>
                <div class="cell">{{ $analysis['summary']['decided'] ?? 0 }} | Abstenciones: {{ $analysis['summary']['abstained'] ?? 0 }}</div>
            </div>
            <div class="row">
                <div class="cell cell-label">Modelo Utilizado</div>
                <div class="cell">{{ $analysis['model_version'] ?? $document->metadata['analysis']['model_version'] ?? 'scitrace-es-academic-detector v1.0.0' }}</div>
            </div>
        </div>
        <div class="alert">
            <strong>Recomendación de revisión IA:</strong> Los fragmentos marcados requieren análisis por parte de un experto humano considerando el contexto disciplinar. Este resultado es cuantitativo pero debe validarse manualmente.
        </div>
    </div>
    @endif

    @if ($plagiarism)
    <div class="section">
        <div class="section-title">🔍 ANÁLISIS DE PLAGIO - Resultados Cuantitativos Específicos</div>
        <div class="grid">
            <div class="row">
                <div class="cell cell-label">Porcentaje Ponderado del Documento</div>
                <div class="cell highlight"><strong>{{ number_format($plagiarism['percentage'] ?? 0, 2, ',', '.') }} %</strong></div>
            </div>
            <div class="row">
                <div class="cell cell-label">Similitud Máxima Detectada</div>
                <div class="cell">{{ number_format(($plagiarism['score'] ?? 0) * 100, 2, ',', '.') }} %</div>
            </div>
            <div class="row">
                <div class="cell cell-label">Oraciones Completas Coincidentes</div>
                <div class="cell">{{ $plagiarism['total_matches'] ?? 0 }}</div>
            </div>
            <div class="row">
                <div class="cell cell-label">Oraciones Completas Analizadas</div>
                <div class="cell">{{ $plagiarism['fragments_analyzed'] ?? 0 }}</div>
            </div>
            <div class="row">
                <div class="cell cell-label">Diagnóstico Cuantitativo</div>
                <div class="cell">{{ $plagiarism['summary'] ?? '' }}</div>
            </div>
        </div>
        @if (! empty($plagiarism['matches']))
        <div style="margin-top: 12px;">
            <div style="font-weight: bold; font-size: 12px; margin-bottom: 6px;">Detallado de Oraciones Completas con Similitud</div>
            @foreach ($plagiarism['matches'] as $index => $match)
            <div class="match-box">
                <div class="match-header">COINCIDENCIA #{{ $index + 1 }} - {{ $match['type'] === 'internal' ? 'Repetición Interna' : 'Similitud Sospechosa' }} | {{ number_format(($match['score'] ?? 0) * 100, 1, ',', '.') }} % de similitud</div>
                <div class="match-text"><strong>Oración completa analizada:</strong> "{{ $match['full_text'] ?? $match['text'] ?? '' }}"</div>
                @if (! empty($match['sources']))
                <div style="margin-top: 6px;">
                    <div style="font-size: 10px; font-weight: bold; color: #0369a1; margin-bottom: 3px;">🔗 FUENTES ACADÉMICAS SUGERIDAS (Enlaces Validados):</div>
                    @foreach ($match['sources'] as $source)
                    <div class="source-box">
                        <div class="source-link">{{ $source['url'] }}</div>
                        <div style="font-size: 9px; color: #64748b;">{{ $source['title'] ?? 'Fuente académica validada' }}</div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>
    @endif

    <div class="section">
        <div class="section-title">✅ ESTADO DEL ANÁLISIS</div>
        <div class="grid">
            <div class="row">
                <div class="cell cell-label">Estado de Procesamiento</div>
                <div class="cell">
                    @if ($document->status === \App\Enums\DocumentStatus::Ready && isset($analysis) && isset($plagiarism))
                        <strong style="color: #166534;">COMPLETADO - Todos los análisis finalizados</strong>
                    @elseif ($document->status === \App\Enums\DocumentStatus::Ready && isset($analysis))
                        <strong style="color: #92400e;">PARCIAL - Detección de IA completada, plagio pendiente o incompleto</strong>
                    @elseif ($document->status === \App\Enums\DocumentStatus::Ready && isset($plagiarism))
                        <strong style="color: #92400e;">PARCIAL - Detección de plagio completada, IA pendiente o incompleto</strong>
                    @elseif ($document->status === \App\Enums\DocumentStatus::Ready)
                        <strong style="color: #92400e;">PARCIAL - Solo extracción completada</strong>
                    @elseif ($document->status === \App\Enums\DocumentStatus::Processing)
                        <strong style="color: #0369a1;">EN PROCESO - Análisis en ejecución</strong>
                    @elseif ($document->status === \App\Enums\DocumentStatus::Failed)
                        <strong style="color: #991b1b;">FALLIDO - Requiere reprocesamiento</strong>
                    @else
                        <strong style="color: #64748b;">PENDIENTE - En cola de procesamiento</strong>
                    @endif
                </div>
            </div>
            <div class="row">
                <div class="cell cell-label">Componentes Analizados</div>
                <div class="cell">
                    Extracción: {{ isset($extraction) && !empty($extraction) ? '✓' : '✗' }} | 
                    Detección IA: {{ isset($analysis) ? '✓' : '✗' }} | 
                    Análisis Plagio: {{ isset($plagiarism) ? '✓' : '✗' }}
                </div>
            </div>
        </div>
    </div>

    <div class="section">
        <div class="section-title">Recomendación Final</div>
        <p>Esta ficha presenta resultados cuantitativos obtenidos mediante análisis automatizado con umbrales calibrados. Todos los indicios deben ser validados por revisión humana considerando el contexto académico y disciplinar. No constituye prueba fehaciente de autoría o plagio.</p>
    </div>
</body>
</html>
