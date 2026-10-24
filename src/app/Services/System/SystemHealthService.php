<?php

namespace App\Services\System;

use App\Services\Ai\AiServiceClient;
use App\Services\Ai\AiServiceException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SystemHealthService
{
    public function __construct(private readonly AiServiceClient $ai) {}

    /**
     * @return array{
     *     overall: string,
     *     checked_at: string,
     *     checks: array<string, array{label: string, status: string, latency_ms: int|null, detail: string}>
     * }
     */
    public function snapshot(): array
    {
        $checks = [
            'database' => $this->probe('PostgreSQL', function (): string {
                DB::select('select 1');

                return 'Conexión disponible';
            }),
            'cache' => $this->probe('Redis / caché', function (): string {
                $key = 'scitrace:health:'.now()->timestamp;
                Cache::put($key, 'ok', 10);

                if (Cache::get($key) !== 'ok') {
                    throw new \RuntimeException('La caché no devolvió el valor de prueba.');
                }

                Cache::forget($key);

                return 'Lectura y escritura disponibles';
            }),
            'ai' => $this->probe('Motor de IA', function (): string {
                $health = $this->ai->health();

                $ready = ($health['detector_ready'] ?? false) === true;
                $version = (string) ($health['version'] ?? 'desconocida');

                return $ready
                    ? "Motor {$version} con detector activo"
                    : "Motor {$version} disponible; detector aún no operativo";
            }),
        ];

        $failedCore = in_array($checks['database']['status'], ['failed'], true)
            || in_array($checks['cache']['status'], ['failed'], true);

        $overall = $failedCore
            ? 'failed'
            : ($checks['ai']['status'] === 'failed' ? 'degraded' : 'ok');

        return [
            'overall' => $overall,
            'checked_at' => now()->toIso8601String(),
            'checks' => $checks,
        ];
    }

    /**
     * @param  callable(): string  $callback
     * @return array{label: string, status: string, latency_ms: int|null, detail: string}
     */
    private function probe(string $label, callable $callback): array
    {
        $start = hrtime(true);

        try {
            $detail = $callback();
            $status = 'ok';
        } catch (AiServiceException $exception) {
            $status = 'failed';
            $detail = $exception->getMessage();
            Log::warning('Fallo de salud del servicio de IA.', [
                'error_code' => $exception->errorCode,
            ]);
        } catch (Throwable $exception) {
            $status = 'failed';
            $detail = 'No disponible';
            Log::warning('Fallo de salud del sistema.', [
                'check' => $label,
                'error' => $exception->getMessage(),
            ]);
        }

        $latency = (int) round((hrtime(true) - $start) / 1_000_000);

        return [
            'label' => $label,
            'status' => $status,
            'latency_ms' => $latency,
            'detail' => $detail,
        ];
    }
}
