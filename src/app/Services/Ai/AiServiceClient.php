<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AiServiceClient
{
    /**
     * @return array<string, mixed>
     */
    public function health(): array
    {
        return $this->request('GET', '/internal/v1/health');
    }

    /**
     * @return array<string, mixed>
     */
    public function capabilities(): array
    {
        return $this->request('GET', '/internal/v1/capabilities');
    }

    /**
     * Envía el documento en memoria al microservicio de extracción.
     *
     * @return array<string, mixed>
     */
    public function extractDocument(string $filename, string $contents, string $mimeType): array
    {
        return $this->request(
            'POST',
            '/internal/v1/documents/extract',
            [
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => $contents,
                        'filename' => $filename,
                        'headers' => ['Content-Type' => $mimeType],
                    ],
                ],
            ],
            (int) config('services.scitrace_ai.extraction_timeout', 120),
        );
    }

    public function isConfigured(): bool
    {
        return filled(config('services.scitrace_ai.token'));
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $options = [], ?int $timeout = null): array
    {
        $baseUrl = rtrim((string) config('services.scitrace_ai.base_url'), '/');
        $token = (string) config('services.scitrace_ai.token');

        if ($baseUrl === '' || $token === '') {
            throw new AiServiceException(
                'El servicio de IA no está configurado.',
                'ai_not_configured',
            );
        }

        try {
            $response = Http::baseUrl($baseUrl)
                ->withHeaders([
                    'X-Internal-Token' => $token,
                    'X-Request-ID' => (string) Str::uuid(),
                    'Accept' => 'application/json',
                ])
                ->connectTimeout((int) config('services.scitrace_ai.connect_timeout', 2))
                ->timeout($timeout ?? (int) config('services.scitrace_ai.timeout', 5))
                ->send($method, $path, $options);
        } catch (ConnectionException) {
            throw new AiServiceException(
                'No se pudo conectar con el servicio de IA.',
                'ai_connection_failed',
            );
        }

        if ($response->status() === 401) {
            throw new AiServiceException(
                'El servicio de IA rechazó la credencial interna.',
                'ai_auth_failed',
                401,
            );
        }

        if (! $response->successful()) {
            $payload = $response->json();
            $code = is_array($payload) ? ($payload['error']['code'] ?? null) : null;
            $message = is_array($payload) ? ($payload['error']['message'] ?? null) : null;

            throw new AiServiceException(
                is_string($message) && $message !== ''
                    ? $message
                    : 'El servicio de IA devolvió un error.',
                is_string($code) && $code !== '' ? $code : 'ai_http_error',
                $response->status(),
            );
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new AiServiceException(
                'La respuesta del servicio de IA no es válida.',
                'ai_invalid_response',
                $response->status(),
            );
        }

        return $payload;
    }
}
