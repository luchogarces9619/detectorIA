<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $started = hrtime(true);
        $database = 'ok';

        try {
            DB::select('select 1');
        } catch (Throwable) {
            $database = 'failed';
        }

        $healthy = $database === 'ok';

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'service' => 'scitrace-web',
            'version' => (string) config('app.version', '0.1.0'),
            'checks' => [
                'database' => $database,
            ],
            'latency_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503, [], JSON_UNESCAPED_UNICODE);
    }
}
