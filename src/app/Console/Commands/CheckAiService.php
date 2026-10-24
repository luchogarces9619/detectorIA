<?php

namespace App\Console\Commands;

use App\Services\Ai\AiServiceClient;
use App\Services\Ai\AiServiceException;
use Illuminate\Console\Command;

class CheckAiService extends Command
{
    protected $signature = 'scitrace:check-ai';

    protected $description = 'Verifica la comunicación autenticada con el microservicio de IA.';

    public function handle(AiServiceClient $client): int
    {
        if (! $client->isConfigured()) {
            $this->components->error('AI_INTERNAL_TOKEN no está configurado.');

            return self::FAILURE;
        }

        try {
            $health = $client->health();
            $capabilities = $client->capabilities();
        } catch (AiServiceException $exception) {
            $this->components->error($exception->getMessage());
            $this->line('Código: '.$exception->errorCode);

            return self::FAILURE;
        }

        $detectorReady = (bool) ($health['detector_ready'] ?? false);
        $activeModel = $health['active_model'] ?? null;

        $this->components->info('Comunicación con el motor de IA verificada.');
        $this->table(['Campo', 'Valor'], [
            ['Servicio', (string) ($health['service'] ?? 'desconocido')],
            ['Versión', (string) ($health['version'] ?? 'desconocida')],
            ['Entorno', (string) ($health['environment'] ?? 'desconocido')],
            ['Detector operativo', $detectorReady ? 'sí' : 'no'],
            ['Modelo activo', is_string($activeModel) && $activeModel !== '' ? $activeModel : 'ninguno'],
            ['Fase declarada', (string) ($capabilities['phase'] ?? 'desconocida')],
        ]);

        $this->newLine();

        if ($detectorReady) {
            $this->components->info('El detector está operativo. Sus resultados son orientativos y no demuestran autoría.');
        } else {
            $this->components->warn('El detector no está operativo. No se deben emitir conclusiones de autoría.');
        }

        return self::SUCCESS;
    }
}
