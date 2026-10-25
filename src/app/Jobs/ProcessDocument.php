<?php

namespace App\Jobs;

use App\Enums\AnalysisConclusion;
use App\Enums\AnalysisStatus;
use App\Enums\ConfidenceLevel;
use App\Enums\DocumentStatus;
use App\Enums\SectionType;
use App\Enums\SegmentKind;
use App\Enums\SegmentLabel;
use App\Models\Analysis;
use App\Models\Document;
use App\Models\DocumentSection;
use App\Models\ModelVersion;
use App\Models\SegmentPrediction;
use App\Models\TextSegment;
use App\Services\Ai\AiServiceClient;
use App\Services\Ai\AiServiceException;
use App\Services\Audit\AuditLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProcessDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 180;

    /** @var list<int> */
    public array $backoff = [15];

    public function __construct(public string $documentId) {}

    public function handle(AiServiceClient $client): void
    {
        $document = Document::query()->find($this->documentId);

        if ($document === null) {
            return;
        }

        $document->forceFill([
            'status' => DocumentStatus::Processing,
            'failure_code' => null,
            'failure_message' => null,
        ])->save();

        $disk = Storage::disk($document->storage_disk);

        if (! $disk->exists($document->storage_path)) {
            $this->markFailed($document, 'file_missing', 'El archivo del documento ya no está disponible.');

            return;
        }

        $contents = $disk->get($document->storage_path);

        if (! is_string($contents) || ! hash_equals($document->sha256, hash('sha256', $contents))) {
            $this->markFailed(
                $document,
                'integrity_check_failed',
                'La verificación de integridad del archivo no coincide con la carga original.',
            );

            return;
        }

        try {
            $payload = $client->extractDocument(
                $document->original_name,
                $contents,
                $document->mime_type,
            );
        } catch (AiServiceException $exception) {
            $this->markFailed($document, $exception->errorCode, $exception->getMessage());

            return;
        }

        try {
            $this->persistExtraction($document, $payload);
        } catch (Throwable $exception) {
            Log::error('No se pudo persistir la extracción documental.', [
                'document_id' => $document->getKey(),
                'error' => $exception->getMessage(),
            ]);

            $this->markFailed(
                $document,
                'persistence_failed',
                'No se pudo guardar la extracción del documento.',
            );

            return;
        }

        try {
            $this->runDetection($document, $client);
        } catch (AiServiceException $exception) {
            if ($exception->statusCode === 503) {
                Log::warning('Detector no disponible; análisis omitido.', [
                    'document_id' => $document->getKey(),
                    'reason' => $exception->getMessage(),
                ]);
            } else {
                Log::error('Error en detección de IA.', [
                    'document_id' => $document->getKey(),
                    'error' => $exception->getMessage(),
                ]);
            }
        } catch (Throwable $exception) {
            Log::error('Error inesperado en detección de IA.', [
                'document_id' => $document->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            $this->runPlagiarism($document, $client);
        } catch (Throwable $exception) {
            Log::error('Error en detección de plagio.', [
                'document_id' => $document->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $document = Document::query()->find($this->documentId);

        if ($document === null) {
            return;
        }

        $this->markFailed(
            $document,
            'processing_failed',
            'El procesamiento del documento falló de forma inesperada.',
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function persistExtraction(Document $document, array $payload): void
    {
        $sections = $this->validatedSections($payload);
        $segments = $this->validatedSegments($payload);

        $stats = is_array($payload['stats'] ?? null) ? $payload['stats'] : [];
        $pageCount = isset($stats['page_count']) && is_numeric($stats['page_count'])
            ? max(1, (int) $stats['page_count'])
            : null;

        $engine = is_string($payload['engine'] ?? null) ? mb_substr($payload['engine'], 0, 50) : 'unknown';
        $language = is_string($payload['language'] ?? null) ? mb_substr($payload['language'], 0, 8) : null;
        $ocrPages = $this->integerList($payload['ocr_pages'] ?? []);
        $warnings = $this->stringList($payload['warnings'] ?? [], 200, 50);

        DB::transaction(function () use (
            $document,
            $sections,
            $segments,
            $pageCount,
            $engine,
            $language,
            $ocrPages,
            $warnings,
        ): void {
            TextSegment::query()->where('document_id', $document->getKey())->delete();
            DocumentSection::query()->where('document_id', $document->getKey())->delete();

            $sectionIds = [];

            foreach ($sections as $section) {
                $created = DocumentSection::create([
                    'document_id' => $document->getKey(),
                    'position' => $section['position'],
                    'level' => $section['level'],
                    'type' => $section['type'],
                    'title' => $section['title'],
                    'char_start' => $section['char_start'],
                    'char_end' => $section['char_end'],
                ]);

                $sectionIds[$section['position']] = $created->getKey();
            }

            $wordCount = 0;
            $paragraphCount = 0;

            foreach ($segments as $segment) {
                $wordCount += $segment['word_count'];

                if (in_array($segment['kind'], [SegmentKind::Paragraph, SegmentKind::ListItem], true)) {
                    $paragraphCount++;
                }

                TextSegment::create([
                    'document_id' => $document->getKey(),
                    'section_id' => $segment['section_position'] !== null
                        ? ($sectionIds[$segment['section_position']] ?? null)
                        : null,
                    'position' => $segment['position'],
                    'kind' => $segment['kind'],
                    'text' => $segment['text'],
                    'content_hash' => $segment['content_hash'],
                    'char_start' => $segment['char_start'],
                    'char_end' => $segment['char_end'],
                    'word_count' => $segment['word_count'],
                    'page_start' => $segment['page_start'],
                    'page_end' => $segment['page_end'],
                    'is_excluded' => $segment['is_excluded'],
                    'exclusion_reason' => $segment['exclusion_reason'],
                ]);
            }

            $document->forceFill([
                'status' => DocumentStatus::Ready,
                'page_count' => $pageCount,
                'word_count' => $wordCount,
                'paragraph_count' => $paragraphCount,
                'language' => $language,
                'failure_code' => null,
                'failure_message' => null,
                'processed_at' => now(),
                'metadata' => array_merge($document->metadata ?? [], [
                    'extraction' => [
                        'engine' => $engine,
                        'ocr_used' => (bool) ($payload['ocr_used'] ?? false),
                        'ocr_pages' => $ocrPages,
                        'warnings' => $warnings,
                        'section_count' => count($sections),
                        'segment_count' => count($segments),
                        'extracted_at' => now()->toIso8601String(),
                    ],
                ]),
            ])->save();
        });

        app(AuditLogger::class)->log('document.processed', subject: $document, metadata: [
            'engine' => $engine,
            'segments' => count($segments),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{position: int, type: SectionType, title: string|null, level: int, char_start: int, char_end: int}>
     */
    private function validatedSections(array $payload): array
    {
        $raw = $payload['sections'] ?? null;

        if (! is_array($raw) || count($raw) > 2000) {
            throw new RuntimeException('Secciones inválidas en la respuesta del servicio.');
        }

        $sections = [];

        foreach ($raw as $index => $section) {
            if (! is_array($section)) {
                throw new RuntimeException('Sección inválida en la respuesta del servicio.');
            }

            $position = isset($section['position']) && is_numeric($section['position'])
                ? (int) $section['position']
                : (int) $index;

            $type = is_string($section['type'] ?? null)
                ? (SectionType::tryFrom($section['type']) ?? SectionType::Other)
                : SectionType::Other;

            $title = is_string($section['title'] ?? null)
                ? mb_substr(trim($section['title']), 0, 500)
                : null;

            $sections[] = [
                'position' => max(0, $position),
                'type' => $type,
                'title' => $title !== '' ? $title : null,
                'level' => max(1, min(6, (int) ($section['level'] ?? 1))),
                'char_start' => max(0, (int) ($section['char_start'] ?? 0)),
                'char_end' => max(0, (int) ($section['char_end'] ?? 0)),
            ];
        }

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{
     *     position: int,
     *     section_position: int|null,
     *     kind: SegmentKind,
     *     text: string,
     *     content_hash: string,
     *     word_count: int,
     *     char_start: int,
     *     char_end: int,
     *     page_start: int|null,
     *     page_end: int|null,
     *     is_excluded: bool,
     *     exclusion_reason: string|null
     * }>
     */
    private function validatedSegments(array $payload): array
    {
        $raw = $payload['segments'] ?? null;
        $maxSegments = (int) config('scitrace.documents.max_segments', 10000);
        $maxChars = (int) config('scitrace.documents.max_segment_chars', 200000);

        if (! is_array($raw) || count($raw) > $maxSegments) {
            throw new RuntimeException('Fragmentos inválidos en la respuesta del servicio.');
        }

        $segments = [];

        foreach ($raw as $index => $segment) {
            if (! is_array($segment) || ! is_string($segment['text'] ?? null)) {
                throw new RuntimeException('Fragmento inválido en la respuesta del servicio.');
            }

            $text = trim($segment['text']);

            if ($text === '' || mb_strlen($text) > $maxChars) {
                throw new RuntimeException('Fragmento vacío o demasiado extenso.');
            }

            $kind = is_string($segment['kind'] ?? null)
                ? (SegmentKind::tryFrom($segment['kind']) ?? SegmentKind::Other)
                : SegmentKind::Other;

            $sectionPosition = isset($segment['section_position']) && is_numeric($segment['section_position'])
                ? max(0, (int) $segment['section_position'])
                : null;

            $reason = is_string($segment['exclusion_reason'] ?? null)
                ? mb_substr($segment['exclusion_reason'], 0, 100)
                : null;

            $segments[] = [
                'position' => max(0, isset($segment['position']) && is_numeric($segment['position'])
                    ? (int) $segment['position']
                    : (int) $index),
                'section_position' => $sectionPosition,
                'kind' => $kind,
                'text' => $text,
                'content_hash' => hash('sha256', $text),
                'word_count' => $this->wordCount($text),
                'char_start' => max(0, (int) ($segment['char_start'] ?? 0)),
                'char_end' => max(0, (int) ($segment['char_end'] ?? 0)),
                'page_start' => isset($segment['page_start']) && is_numeric($segment['page_start'])
                    ? max(1, (int) $segment['page_start'])
                    : null,
                'page_end' => isset($segment['page_end']) && is_numeric($segment['page_end'])
                    ? max(1, (int) $segment['page_end'])
                    : null,
                'is_excluded' => (bool) ($segment['is_excluded'] ?? false),
                'exclusion_reason' => $reason,
            ];
        }

        return $segments;
    }

    private function wordCount(string $text): int
    {
        return (int) preg_match_all('/[\p{L}\p{N}_]+/u', $text);
    }

    /**
     * @return list<int>
     */
    private function integerList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (mixed $item): int => (int) $item, $value),
            fn (int $item): bool => $item > 0,
        ));
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value, int $maxLength, int $maxItems): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach (array_slice($value, 0, $maxItems) as $item) {
            if (is_string($item) && $item !== '') {
                $items[] = mb_substr($item, 0, $maxLength);
            }
        }

        return $items;
    }

    private function markFailed(Document $document, string $code, string $message): void
    {
        $document->forceFill([
            'status' => DocumentStatus::Failed,
            'failure_code' => mb_substr($code, 0, 50),
            'failure_message' => mb_substr($message, 0, 1000),
            'processed_at' => now(),
        ])->save();

        app(AuditLogger::class)->log('document.processing_failed', subject: $document, metadata: [
            'code' => mb_substr($code, 0, 50),
        ]);
    }

    /**
     * Ejecuta la detección de IA sobre los segmentos no excluidos del documento.
     */
    private function runDetection(Document $document, AiServiceClient $client): void
    {
        $segments = TextSegment::query()
            ->where('document_id', $document->getKey())
            ->where('is_excluded', false)
            ->orderBy('position')
            ->get(['id', 'text', 'word_count']);

        if ($segments->isEmpty()) {
            Log::info('No hay segmentos analizables para detección.', [
                'document_id' => $document->getKey(),
            ]);

            return;
        }

        $segmentInputs = $segments->map(fn (TextSegment $s) => [
            'text' => $s->text,
            'segment_id' => (string) $s->getKey(),
        ])->toArray();

        try {
            $detection = $client->detectText($segmentInputs, (string) $document->getKey());
        } catch (AiServiceException $exception) {
            if ($exception->statusCode === 503) {
                Log::warning('Detector no disponible; análisis omitido.', [
                    'document_id' => $document->getKey(),
                    'reason' => $exception->getMessage(),
                ]);
            } else {
                Log::error('Error en detección de IA.', [
                    'document_id' => $document->getKey(),
                    'error' => $exception->getMessage(),
                ]);
            }

            return;
        } catch (Throwable $exception) {
            Log::error('Error inesperado en detección de IA.', [
                'document_id' => $document->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        if (! is_array($detection)) {
            Log::warning('Respuesta de detección inválida; análisis omitido.', [
                'document_id' => $document->getKey(),
            ]);

            return;
        }

        $model = $detection['model'] ?? [];
        $docResult = $detection['document'] ?? [];
        $segmentResults = $detection['segments'] ?? [];

        if (! is_array($segmentResults) || count($segmentResults) === 0) {
            Log::warning('Sin resultados de segmentos en detección; análisis omitido.', [
                'document_id' => $document->getKey(),
            ]);

            return;
        }

        $modelVersion = $this->resolveModelVersion($model);

        $analysis = Analysis::create([
            'document_id' => $document->getKey(),
            'user_id' => $document->user_id,
            'model_version_id' => $modelVersion?->getKey(),
            'engine_version' => $model['version'] ?? '1.0.0',
            'document_sha256' => $document->sha256,
            'status' => AnalysisStatus::Completed,
            'confidence' => ConfidenceLevel::tryFrom($docResult['confidence'] ?? 'insufficient') ?? ConfidenceLevel::Insufficient,
            'conclusion' => AnalysisConclusion::tryFrom($docResult['conclusion'] ?? 'not_conclusive') ?? AnalysisConclusion::NotConclusive,
            'aggregate_score' => is_numeric($docResult['aggregate_score'] ?? null) ? (float) $docResult['aggregate_score'] : null,
            'summary' => [
                'coverage' => is_numeric($docResult['coverage'] ?? null) ? (float) $docResult['coverage'] : 0.0,
                'decided' => is_int($docResult['decided'] ?? null) ? (int) $docResult['decided'] : 0,
                'abstained' => is_int($docResult['abstained'] ?? null) ? (int) $docResult['abstained'] : 0,
            ],
            'parameters' => [
                'high_threshold' => is_numeric($model['high_threshold'] ?? null) ? (float) $model['high_threshold'] : null,
                'low_threshold' => is_numeric($model['low_threshold'] ?? null) ? (float) $model['low_threshold'] : null,
            ],
            'started_at' => now(),
            'completed_at' => now(),
            'progress' => 100,
        ]);

        foreach ($segmentResults as $index => $result) {
            $segment = $segments[$index] ?? null;
            if ($segment === null) {
                continue;
            }

            SegmentPrediction::create([
                'analysis_id' => $analysis->getKey(),
                'segment_id' => $segment->getKey(),
                'model_version_id' => $modelVersion?->getKey(),
                'score' => is_numeric($result['score'] ?? null) ? (float) $result['score'] : 0.5,
                'label' => SegmentLabel::tryFrom($result['label'] ?? 'uncertain') ?? SegmentLabel::Uncertain,
                'confidence' => ConfidenceLevel::tryFrom($result['confidence'] ?? 'insufficient') ?? ConfidenceLevel::Insufficient,
                'requires_review' => (bool) ($result['requires_review'] ?? true),
                'indicators' => [
                    'word_count' => is_int($result['word_count'] ?? null) ? (int) $result['word_count'] : $segment->word_count,
                    'chunk_count' => is_int($result['chunk_count'] ?? null) ? (int) $result['chunk_count'] : 0,
                ],
            ]);
        }

        $document->forceFill([
            'metadata' => array_merge($document->metadata ?? [], [
                'analysis' => [
                    'conclusion' => $analysis->conclusion->value,
                    'confidence' => $analysis->confidence->value,
                    'aggregate_score' => $analysis->aggregate_score,
                    'analyzed_at' => now()->toIso8601String(),
                    'model_version' => $modelVersion?->version ?? 'unknown',
                ],
            ]),
        ])->save();

        app(AuditLogger::class)->log('document.analyzed', subject: $document, metadata: [
            'analysis_id' => (string) $analysis->getKey(),
            'conclusion' => $analysis->conclusion->value,
            'confidence' => $analysis->confidence->value,
        ]);
    }

    /**
     * Ejecuta la detección de plagio sobre el documento.
     */
    private function runPlagiarism(Document $document, AiServiceClient $client): void
    {
        $segments = TextSegment::query()
            ->where('document_id', $document->getKey())
            ->where('is_excluded', false)
            ->orderBy('position')
            ->get(['id', 'text', 'word_count']);

        if ($segments->isEmpty()) {
            return;
        }

        $fullText = $segments->pluck('text')->implode(' ');
        $segmentTexts = $segments->pluck('text')->toArray();

        try {
            $plagiarism = $client->plagiarismDetect($fullText, $segmentTexts);
        } catch (Throwable) {
            return;
        }

        if (! is_array($plagiarism)) {
            return;
        }

        $document->forceFill([
            'metadata' => array_merge($document->metadata ?? [], [
                'plagiarism' => [
                    'score' => $plagiarism['score'] ?? 0.0,
                    'percentage' => $plagiarism['percentage'] ?? 0.0,
                    'total_matches' => $plagiarism['total_matches'] ?? 0,
                    'summary' => $plagiarism['summary'] ?? '',
                    'matches' => $plagiarism['matches'] ?? [],
                    'fragments_analyzed' => $plagiarism['fragments_analyzed'] ?? 0,
                    'fragments_matched' => $plagiarism['fragments_matched'] ?? 0,
                    'analyzed_at' => now()->toIso8601String(),
                ],
            ]),
        ])->save();
    }

    /**
     * Intenta resolver la versión del modelo desde la base de datos por SHA-256.
     */
    private function resolveModelVersion(array $model): ?ModelVersion
    {
        $sha256 = $model['artifact_sha256'] ?? null;

        if (! is_string($sha256) || $sha256 === '') {
            return null;
        }

        return ModelVersion::query()
            ->where('artifact_sha256', $sha256)
            ->first();
    }
}
