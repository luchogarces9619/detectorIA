<?php

use App\Services\Ai\AiServiceClient;
use App\Services\Ai\AiServiceException;
use App\Services\System\SystemHealthService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.scitrace_ai.base_url' => 'http://ai:8000',
        'services.scitrace_ai.token' => str_repeat('t', 48),
        'services.scitrace_ai.timeout' => 3,
        'services.scitrace_ai.connect_timeout' => 1,
    ]);
});

it('llama al motor de IA con el token interno y el identificador de petición', function () {
    Http::fake([
        'http://ai:8000/internal/v1/health' => Http::response([
            'status' => 'ok',
            'service' => 'SciTrace AI Engine',
            'version' => '0.1.0',
            'environment' => 'test',
            'detector_ready' => false,
            'active_model' => null,
            'timestamp' => now()->toIso8601String(),
        ]),
    ]);

    $health = app(AiServiceClient::class)->health();

    expect($health['status'])->toBe('ok')
        ->and($health['detector_ready'])->toBeFalse();

    Http::assertSent(function ($request) {
        return $request->url() === 'http://ai:8000/internal/v1/health'
            && $request->hasHeader('X-Internal-Token', str_repeat('t', 48))
            && $request->hasHeader('X-Request-ID');
    });
});

it('convierte un error HTTP del motor en una excepción controlada', function () {
    Http::fake([
        'http://ai:8000/internal/v1/health' => Http::response(['detail' => 'boom'], 500),
    ]);

    try {
        app(AiServiceClient::class)->health();
    } catch (AiServiceException $exception) {
        expect($exception->errorCode)->toBe('ai_http_error')
            ->and($exception->statusCode)->toBe(500);

        return;
    }

    $this->fail('Se esperaba AiServiceException.');
});

it('no llama al motor si el token no está configurado', function () {
    config(['services.scitrace_ai.token' => null]);
    Http::fake();

    expect(fn () => app(AiServiceClient::class)->health())
        ->toThrow(AiServiceException::class);

    Http::assertNothingSent();
});

it('verifica el comando de diagnóstico del motor', function () {
    Http::fake([
        'http://ai:8000/internal/v1/health' => Http::response([
            'status' => 'ok',
            'service' => 'SciTrace AI Engine',
            'version' => '0.1.0',
            'environment' => 'test',
            'detector_ready' => false,
        ]),
        'http://ai:8000/internal/v1/capabilities' => Http::response([
            'phase' => '1-fundamentos',
            'detector_ready' => false,
        ]),
    ]);

    $this->artisan('scitrace:check-ai')->assertSuccessful();
});

it('falla el comando si el motor rechaza la credencial', function () {
    Http::fake([
        'http://ai:8000/internal/v1/health' => Http::response([], 401),
    ]);

    $this->artisan('scitrace:check-ai')->assertFailed();
});

it('resume la salud de base de datos, caché y motor de IA', function () {
    Http::fake([
        'http://ai:8000/internal/v1/health' => Http::response([
            'status' => 'ok',
            'version' => '0.1.0',
            'detector_ready' => false,
        ]),
    ]);

    $snapshot = app(SystemHealthService::class)->snapshot();

    expect($snapshot['overall'])->toBe('ok')
        ->and($snapshot['checks']['database']['status'])->toBe('ok')
        ->and($snapshot['checks']['cache']['status'])->toBe('ok')
        ->and($snapshot['checks']['ai']['status'])->toBe('ok');
});

it('marca el sistema como degradado si el motor de IA no responde', function () {
    Http::fake([
        'http://ai:8000/internal/v1/health' => Http::response([], 503),
    ]);

    $snapshot = app(SystemHealthService::class)->snapshot();

    expect($snapshot['overall'])->toBe('degraded')
        ->and($snapshot['checks']['ai']['status'])->toBe('failed');
});
