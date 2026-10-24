<?php

namespace App\Jobs;

use App\Enums\DocumentStatus;
use App\Enums\SectionType;
use App\Enums\SegmentKind;
use App\Models\Document;
use App\Models\DocumentSection;
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
}
