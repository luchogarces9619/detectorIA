<?php

use App\Enums\DocumentStatus;
use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Models\DocumentSection;
use App\Models\TextSegment;
use App\Models\User;
use App\Services\Ai\AiServiceClient;
use App\Services\Ai\AiServiceException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

/**
 * @return array<string, mixed>
 */
function extractionPayload(): array
{
    return [
        'original_name' => 'tesis.docx',
        'extension' => 'docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'size_bytes' => 2048,
        'sha256' => str_repeat('a', 64),
        'engine' => 'python-docx',
        'ocr_used' => false,
        'ocr_pages' => [],
        'language' => 'es',
        'warnings' => ['no_text_page_2'],
        'stats' => [
            'page_count' => null,
            'word_count' => 13,
            'paragraph_count' => 1,
            'section_count' => 2,
            'segment_count' => 3,
            'excluded_segment_count' => 1,
        ],
        'sections' => [
            [
                'position' => 0,
                'type' => 'title',
                'title' => 'Título de la tesis',
                'level' => 1,
                'char_start' => 0,
                'char_end' => 17,
            ],
            [
                'position' => 1,
                'type' => 'references',
                'title' => 'Referencias',
                'level' => 1,
                'char_start' => 18,
                'char_end' => 30,
            ],
        ],
        'segments' => [
            [
                'position' => 0,
                'section_position' => 0,
                'kind' => 'heading',
                'text' => 'Título de la tesis',
                'content_hash' => str_repeat('b', 64),
                'word_count' => 4,
                'char_start' => 0,
                'char_end' => 17,
                'page_start' => null,
                'page_end' => null,
                'is_excluded' => false,
                'exclusion_reason' => null,
            ],
            [
                'position' => 1,
                'section_position' => 0,
                'kind' => 'paragraph',
                'text' => 'Primer párrafo de la investigación.',
                'content_hash' => str_repeat('c', 64),
                'word_count' => 5,
                'char_start' => 18,
                'char_end' => 52,
                'page_start' => null,
                'page_end' => null,
                'is_excluded' => false,
                'exclusion_reason' => null,
            ],
            [
                'position' => 2,
                'section_position' => 1,
                'kind' => 'reference',
                'text' => 'Pérez, J. (2023). Referencia.',
                'content_hash' => str_repeat('d', 64),
                'word_count' => 4,
                'char_start' => 53,
                'char_end' => 80,
                'page_start' => null,
                'page_end' => null,
                'is_excluded' => true,
                'exclusion_reason' => 'references',
            ],
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function detectionPayload(): array
{
    return [
        'model' => [
            'name' => 'scitrace-es-academic-detector',
            'version' => '1.0.0',
            'artifact_sha256' => str_repeat('e', 64),
            'high_threshold' => 0.25,
            'low_threshold' => 0.15,
        ],
        'document' => [
            'aggregate_score' => 0.2,
            'conclusion' => 'no_signals',
            'confidence' => 'medium',
            'coverage' => 1.0,
            'decided' => 2,
            'abstained' => 0,
        ],
        'segments' => [
            [
                'index' => 0,
                'score' => 0.1,
                'label' => 'human',
                'confidence' => 'high',
                'requires_review' => false,
                'word_count' => 4,
                'chunk_count' => 1,
            ],
            [
                'index' => 1,
                'score' => 0.15,
                'label' => 'human',
                'confidence' => 'high',
                'requires_review' => false,
                'word_count' => 5,
                'chunk_count' => 1,
            ],
        ],
        'disclaimer' => 'Resultado orientativo para revisión humana.',
    ];
}

/**
 * @return array{document: Document, client: MockInterface}
 */
function documentWithStoredFile(string $contents = 'contenido original'): array
{
    Storage::fake('local');

    $user = User::factory()->create();
    $document = Document::factory()->for($user)->create([
        'storage_path' => 'documents/'.$user->getKey().'/proceso.docx',
        'sha256' => hash('sha256', $contents),
        'status' => DocumentStatus::Uploaded,
    ]);

    Storage::disk('local')->put($document->storage_path, $contents);

    $client = Mockery::mock(AiServiceClient::class);

    return ['document' => $document, 'client' => $client];
}

it('persiste secciones y fragmentos y marca el documento como listo', function () {
    ['document' => $document, 'client' => $client] = documentWithStoredFile();

    $client->shouldReceive('extractDocument')
        ->once()
        ->with($document->original_name, 'contenido original', $document->mime_type)
        ->andReturn(extractionPayload());

    $client->shouldReceive('detectText')
        ->once()
        ->withAnyArgs()
        ->andReturn(detectionPayload());

    (new ProcessDocument($document->getKey()))->handle($client);

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Ready)
        ->and($document->page_count)->toBeNull()
        ->and($document->word_count)->toBe(13)
        ->and($document->paragraph_count)->toBe(1)
        ->and($document->language)->toBe('es')
        ->and($document->failure_code)->toBeNull()
        ->and($document->processed_at)->not->toBeNull()
        ->and($document->sections)->toHaveCount(2)
        ->and($document->segments)->toHaveCount(3);

    expect($document->metadata['extraction']['engine'])->toBe('python-docx')
        ->and($document->metadata['extraction']['warnings'])->toBe(['no_text_page_2'])
        ->and($document->metadata['extraction']['segment_count'])->toBe(3);

    $titleSection = $document->sections->firstWhere('position', 0);
    $referenceSection = $document->sections->firstWhere('position', 1);

    $paragraph = $document->segments->firstWhere('position', 1);
    $reference = $document->segments->firstWhere('position', 2);

    expect($paragraph->section_id)->toBe($titleSection->getKey())
        ->and($paragraph->content_hash)->toBe(hash('sha256', 'Primer párrafo de la investigación.'))
        ->and($reference->section_id)->toBe($referenceSection->getKey())
        ->and($reference->is_excluded)->toBeTrue()
        ->and($reference->exclusion_reason)->toBe('references');

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'document.processed',
        'subject_id' => $document->getKey(),
    ]);
});

it('reemplaza secciones y fragmentos al reprocesar', function () {
    ['document' => $document, 'client' => $client] = documentWithStoredFile();

    $oldSection = DocumentSection::create([
        'document_id' => $document->getKey(),
        'position' => 99,
        'level' => 1,
        'type' => 'other',
        'title' => 'Sección antigua',
    ]);

    $oldSegment = TextSegment::create([
        'document_id' => $document->getKey(),
        'section_id' => $oldSection->getKey(),
        'position' => 99,
        'kind' => 'paragraph',
        'text' => 'Fragmento antiguo.',
        'content_hash' => hash('sha256', 'Fragmento antiguo.'),
        'word_count' => 2,
        'is_excluded' => false,
    ]);

    $client->shouldReceive('extractDocument')->once()->andReturn(extractionPayload());
    $client->shouldReceive('detectText')->once()->withAnyArgs()->andReturn(detectionPayload());

    (new ProcessDocument($document->getKey()))->handle($client);

    $this->assertDatabaseMissing('document_sections', ['id' => $oldSection->getKey()]);
    $this->assertDatabaseMissing('text_segments', ['id' => $oldSegment->getKey()]);

    expect($document->fresh()->sections()->count())->toBe(2)
        ->and($document->fresh()->segments()->count())->toBe(3);
});

it('marca el documento como fallido cuando el servicio de IA devuelve un error', function () {
    ['document' => $document, 'client' => $client] = documentWithStoredFile();

    $client->shouldReceive('extractDocument')
        ->once()
        ->andThrow(new AiServiceException('OCR no disponible.', 'ocr_unavailable', 422));

    (new ProcessDocument($document->getKey()))->handle($client);

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Failed)
        ->and($document->failure_code)->toBe('ocr_unavailable')
        ->and($document->failure_message)->toBe('OCR no disponible.')
        ->and($document->segments()->count())->toBe(0);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'document.processing_failed',
        'subject_id' => $document->getKey(),
    ]);
});

it('no llama al servicio de IA si el hash del archivo no coincide', function () {
    ['document' => $document, 'client' => $client] = documentWithStoredFile('contenido original');

    Storage::disk('local')->put($document->storage_path, 'contenido alterado');

    $client->shouldNotReceive('extractDocument');

    (new ProcessDocument($document->getKey()))->handle($client);

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Failed)
        ->and($document->failure_code)->toBe('integrity_check_failed');
});

it('marca el documento como fallido si el archivo ya no existe', function () {
    ['document' => $document, 'client' => $client] = documentWithStoredFile();

    Storage::disk('local')->delete($document->storage_path);

    $client->shouldNotReceive('extractDocument');

    (new ProcessDocument($document->getKey()))->handle($client);

    expect($document->fresh()->failure_code)->toBe('file_missing');
});

it('rechaza respuestas malformadas del servicio sin dejar datos parciales', function () {
    ['document' => $document, 'client' => $client] = documentWithStoredFile();

    $client->shouldReceive('extractDocument')->once()->andReturn([
        'sections' => 'esto-no-es-una-lista',
        'segments' => [],
    ]);

    (new ProcessDocument($document->getKey()))->handle($client);

    $document->refresh();

    expect($document->status)->toBe(DocumentStatus::Failed)
        ->and($document->failure_code)->toBe('persistence_failed')
        ->and($document->sections()->count())->toBe(0)
        ->and($document->segments()->count())->toBe(0);
});
