<?php

namespace App\Console\Commands;

use App\Enums\ModelStatus;
use App\Models\ModelVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RegisterModelVersion extends Command
{
    protected $signature = 'scitrace:models:register
        {card : Ruta al archivo card.json del artefacto}
        {--status=candidate : Estado inicial (draft o candidate)}';

    protected $description = 'Registra o actualiza la versión de un artefacto de detector verificado.';

    public function handle(): int
    {
        $cardPath = realpath((string) $this->argument('card'));

        if ($cardPath === false || ! is_file($cardPath)) {
            $this->components->error('No existe el archivo card.json indicado.');

            return self::FAILURE;
        }

        $card = json_decode((string) file_get_contents($cardPath), true);

        if (! is_array($card)) {
            $this->components->error('card.json no contiene JSON válido.');

            return self::FAILURE;
        }

        foreach (['name', 'version', 'artifact', 'artifact_sha256'] as $key) {
            if (! isset($card[$key]) || ! is_string($card[$key]) || $card[$key] === '') {
                $this->components->error("card.json no contiene el campo obligatorio '{$key}'.");

                return self::FAILURE;
            }
        }

        if (preg_match('/^[a-f0-9]{64}$/', $card['artifact_sha256']) !== 1) {
            $this->components->error('artifact_sha256 no es un SHA-256 hexadecimal válido.');

            return self::FAILURE;
        }

        $artifactPath = dirname($cardPath).DIRECTORY_SEPARATOR.$card['artifact'];

        if (! is_file($artifactPath)) {
            $this->components->error('No existe el artefacto indicado junto a card.json.');

            return self::FAILURE;
        }

        $actualHash = hash_file('sha256', $artifactPath);

        if (! is_string($actualHash) || ! hash_equals($card['artifact_sha256'], $actualHash)) {
            $this->components->error('El SHA-256 del artefacto no coincide con card.json.');

            return self::FAILURE;
        }

        $status = ModelStatus::tryFrom((string) $this->option('status'));

        if (! in_array($status, [ModelStatus::Draft, ModelStatus::Candidate], true)) {
            $this->components->error('El estado inicial solo puede ser draft o candidate.');

            return self::FAILURE;
        }

        $existing = ModelVersion::query()
            ->where('name', $card['name'])
            ->where('version', $card['version'])
            ->first();

        if ($existing !== null && $existing->artifact_sha256 !== $card['artifact_sha256']) {
            $this->components->error(
                'Ya existe esa versión con otro artefacto. Registre una versión nueva.'
            );

            return self::FAILURE;
        }

        $config = is_array($card['config'] ?? null) ? $card['config'] : [];
        $config['thresholds'] = $card['thresholds'] ?? null;
        $config['dataset'] = $card['dataset'] ?? null;
        $config['environment'] = $card['environment'] ?? null;

        $notes = is_array($card['limitations'] ?? null)
            ? implode("\n", array_map('strval', $card['limitations']))
            : null;

        $attributes = [
            'provider' => 'scitrace-local',
            'task' => 'text-classification',
            'artifact_path' => $artifactPath,
            'artifact_sha256' => $card['artifact_sha256'],
            'config' => $config,
            'metrics' => is_array($card['metrics'] ?? null) ? $card['metrics'] : [],
            'trained_at' => $card['created_at'] ?? null,
            'notes' => $notes,
        ];

        $model = DB::transaction(function () use ($existing, $attributes, $status, $card) {
            if ($existing !== null) {
                $existing->fill($attributes);

                if ($existing->status === ModelStatus::Draft) {
                    $existing->status = $status;
                }

                $existing->save();

                return $existing->fresh();
            }

            return ModelVersion::query()->create([
                'name' => $card['name'],
                'version' => $card['version'],
                'status' => $status,
                ...$attributes,
            ]);
        });

        $this->components->info(
            $existing !== null ? 'Versión actualizada.' : 'Versión registrada como candidata.'
        );

        $this->table(['Campo', 'Valor'], [
            ['Nombre', $model->name],
            ['Versión', $model->version],
            ['Estado', $model->status->value],
            ['SHA-256', $model->artifact_sha256],
            ['Artefacto', $model->artifact_path],
        ]);

        $this->newLine();
        $this->warn(
            'El motor carga AI_MODEL_DIR, no este registro. '
            .'La activación y la orquestación del análisis corresponden a la Fase 4.'
        );

        return self::SUCCESS;
    }
}
