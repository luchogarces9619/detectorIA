<?php

use App\Enums\AnalysisStatus;
use App\Enums\DocumentStatus;
use App\Enums\SegmentLabel;
use App\Models\Analysis;
use App\Models\Document;
use App\Models\DocumentSection;
use App\Models\ModelVersion;
use App\Models\Report;
use App\Models\SegmentPrediction;
use App\Models\TextSegment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('permite varios análisis del mismo documento con versiones distintas del modelo', function () {
    $user = User::factory()->create();

    $document = Document::create([
        'user_id' => $user->id,
        'original_name' => 'tesis.docx',
        'storage_path' => 'documents/'.$user->id.'/tesis.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'extension' => 'docx',
        'size_bytes' => 1024,
        'sha256' => str_repeat('a', 64),
        'status' => DocumentStatus::Ready,
        'uploaded_at' => now(),
    ]);

    $versionOne = ModelVersion::create([
        'name' => 'scitrace-es-academic',
        'version' => '0.1.0',
        'status' => 'candidate',
    ]);

    $versionTwo = ModelVersion::create([
        'name' => 'scitrace-es-academic',
        'version' => '0.2.0',
        'status' => 'candidate',
    ]);

    foreach ([$versionOne, $versionTwo] as $modelVersion) {
        Analysis::create([
            'document_id' => $document->id,
            'user_id' => $user->id,
            'model_version_id' => $modelVersion->id,
            'engine_version' => '0.1.0',
            'status' => AnalysisStatus::Queued,
            'document_sha256' => $document->sha256,
        ]);
    }

    expect($document->analyses()->count())->toBe(2)
        ->and($document->analyses()->where('model_version_id', $versionTwo->id)->exists())->toBeTrue();
});

it('mantiene la integridad de secciones, fragmentos, predicciones e informes', function () {
    $user = User::factory()->create();

    $document = Document::create([
        'user_id' => $user->id,
        'original_name' => 'articulo.pdf',
        'storage_path' => 'documents/'.$user->id.'/articulo.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size_bytes' => 2048,
        'sha256' => str_repeat('b', 64),
        'status' => DocumentStatus::Ready,
        'page_count' => 10,
        'uploaded_at' => now(),
    ]);

    $section = DocumentSection::create([
        'document_id' => $document->id,
        'position' => 1,
        'type' => 'introduction',
        'title' => 'Introducción',
    ]);

    $segment = TextSegment::create([
        'document_id' => $document->id,
        'section_id' => $section->id,
        'position' => 1,
        'kind' => 'paragraph',
        'text' => 'Texto académico de prueba.',
        'content_hash' => hash('sha256', 'Texto académico de prueba.'),
        'word_count' => 4,
    ]);

    $version = ModelVersion::create([
        'name' => 'scitrace-es-academic',
        'version' => '0.1.0',
    ]);

    $analysis = Analysis::create([
        'document_id' => $document->id,
        'user_id' => $user->id,
        'model_version_id' => $version->id,
        'engine_version' => '0.1.0',
        'status' => AnalysisStatus::Completed,
        'document_sha256' => $document->sha256,
        'conclusion' => 'not_conclusive',
        'confidence' => 'insufficient',
    ]);

    $prediction = SegmentPrediction::create([
        'analysis_id' => $analysis->id,
        'segment_id' => $segment->id,
        'model_version_id' => $version->id,
        'label' => SegmentLabel::Uncertain,
        'requires_review' => false,
        'indicators' => [['key' => 'perplexity', 'value' => 42.1]],
    ]);

    $report = Report::create([
        'analysis_id' => $analysis->id,
        'user_id' => $user->id,
        'storage_path' => 'reports/'.$analysis->id.'/informe.pdf',
        'sha256' => str_repeat('c', 64),
        'generated_at' => now(),
    ]);

    expect($segment->section->is($section))->toBeTrue()
        ->and($prediction->segment->is($segment))->toBeTrue()
        ->and($analysis->predictions()->count())->toBe(1)
        ->and($analysis->reports()->count())->toBe(1)
        ->and($report->analysis->is($analysis))->toBeTrue();
});

it('elimina en cascada los datos derivados al borrar definitivamente un documento', function () {
    $user = User::factory()->create();

    $document = Document::create([
        'user_id' => $user->id,
        'original_name' => 'borrar.pdf',
        'storage_path' => 'documents/'.$user->id.'/borrar.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'size_bytes' => 100,
        'sha256' => str_repeat('d', 64),
    ]);

    $section = DocumentSection::create([
        'document_id' => $document->id,
        'position' => 1,
    ]);

    $segment = TextSegment::create([
        'document_id' => $document->id,
        'section_id' => $section->id,
        'position' => 1,
        'text' => 'Contenido.',
        'content_hash' => hash('sha256', 'Contenido.'),
    ]);

    $analysis = Analysis::create([
        'document_id' => $document->id,
        'user_id' => $user->id,
        'engine_version' => '0.1.0',
        'document_sha256' => $document->sha256,
    ]);

    SegmentPrediction::create([
        'analysis_id' => $analysis->id,
        'segment_id' => $segment->id,
    ]);

    $document->forceDelete();

    $this->assertDatabaseMissing('documents', ['id' => $document->id]);
    $this->assertDatabaseMissing('document_sections', ['id' => $section->id]);
    $this->assertDatabaseMissing('text_segments', ['id' => $segment->id]);
    $this->assertDatabaseMissing('analyses', ['id' => $analysis->id]);
});
