<?php

use App\Enums\ModelStatus;
use App\Models\ModelVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeModelCardDirectory(
    string $version = '1.0.0',
    string $contents = 'artifact-bytes'
): array {
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'scitrace-model-'.bin2hex(random_bytes(6));
    mkdir($directory, 0777, true);
    file_put_contents($directory.DIRECTORY_SEPARATOR.'model.joblib', $contents);

    $card = [
        'name' => 'scitrace-es-academic-detector',
        'version' => $version,
        'artifact' => 'model.joblib',
        'artifact_sha256' => hash('sha256', $contents),
        'config' => ['C' => 2.0],
        'metrics' => ['test' => ['document_level' => ['roc_auc' => 0.9]]],
        'thresholds' => ['high' => 0.8, 'low' => 0.2, 'valid' => true],
        'dataset' => ['pairs' => 100],
        'environment' => ['python' => '3.12.15'],
        'created_at' => '2026-10-23T00:00:00+00:00',
        'limitations' => ['Resultado orientativo.'],
    ];

    file_put_contents(
        $directory.DIRECTORY_SEPARATOR.'card.json',
        json_encode($card, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
    );

    return [$directory, $card];
}

it('registra un artefacto verificado como candidato', function () {
    [$directory, $card] = makeModelCardDirectory();

    $this->artisan('scitrace:models:register', [
        'card' => $directory.DIRECTORY_SEPARATOR.'card.json',
    ])->assertSuccessful();

    $model = ModelVersion::query()->firstOrFail();

    expect($model->name)->toBe('scitrace-es-academic-detector')
        ->and($model->version)->toBe('1.0.0')
        ->and($model->status)->toBe(ModelStatus::Candidate)
        ->and($model->artifact_sha256)->toBe($card['artifact_sha256'])
        ->and($model->config['thresholds']['high'])->toBe(0.8)
        ->and($model->metrics['test']['document_level']['roc_auc'])->toBe(0.9)
        ->and($model->notes)->toContain('orientativo');
});

it('es idempotente con el mismo artefacto', function () {
    [$directory] = makeModelCardDirectory();

    $this->artisan('scitrace:models:register', [
        'card' => $directory.DIRECTORY_SEPARATOR.'card.json',
    ])->assertSuccessful();

    $this->artisan('scitrace:models:register', [
        'card' => $directory.DIRECTORY_SEPARATOR.'card.json',
    ])->assertSuccessful();

    expect(ModelVersion::query()->count())->toBe(1);
});

it('rechaza un artefacto alterado', function () {
    [$directory] = makeModelCardDirectory();
    file_put_contents($directory.DIRECTORY_SEPARATOR.'model.joblib', 'alterado');

    $this->artisan('scitrace:models:register', [
        'card' => $directory.DIRECTORY_SEPARATOR.'card.json',
    ])->assertFailed();

    expect(ModelVersion::query()->count())->toBe(0);
});

it('rechaza reutilizar una versión con otro artefacto', function () {
    [$first] = makeModelCardDirectory('1.0.0', 'primer-artefacto');
    [$second] = makeModelCardDirectory('1.0.0', 'segundo-artefacto');

    $this->artisan('scitrace:models:register', [
        'card' => $first.DIRECTORY_SEPARATOR.'card.json',
    ])->assertSuccessful();

    $this->artisan('scitrace:models:register', [
        'card' => $second.DIRECTORY_SEPARATOR.'card.json',
    ])->assertFailed();

    $model = ModelVersion::query()->firstOrFail();

    expect(ModelVersion::query()->count())->toBe(1)
        ->and($model->artifact_sha256)->toBe(hash('sha256', 'primer-artefacto'));
});
