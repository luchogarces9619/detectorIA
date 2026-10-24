<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditLogger
{
    /**
     * Registra un evento de seguridad sin almacenar el contenido de documentos
     * ni credenciales. El fallo del registro nunca interrumpe la operación.
     *
     * @param  array<string, scalar|null>  $metadata
     */
    public function log(
        string $action,
        ?User $user = null,
        ?Model $subject = null,
        array $metadata = [],
    ): void {
        try {
            $request = request();
            $actor = $user ?? Auth::user();

            AuditLog::create([
                'user_id' => $actor?->getKey(),
                'action' => $action,
                'subject_type' => $subject ? $subject::class : null,
                'subject_id' => $subject?->getKey(),
                'ip_address' => $request?->ip(),
                'user_agent' => mb_substr((string) $request?->userAgent(), 0, 500),
                'metadata' => $this->sanitize($metadata),
            ]);
        } catch (Throwable $exception) {
            Log::warning('No se pudo registrar el evento de auditoría.', [
                'action' => $action,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, scalar|null>  $metadata
     * @return array<string, scalar|null>
     */
    private function sanitize(array $metadata): array
    {
        $clean = [];

        foreach (array_slice($metadata, 0, 20, true) as $key => $value) {
            if (! is_scalar($value) && $value !== null) {
                continue;
            }

            $clean[mb_substr((string) $key, 0, 50)] = is_string($value)
                ? mb_substr($value, 0, 500)
                : $value;
        }

        return $clean;
    }
}
