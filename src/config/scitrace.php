<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Administrador inicial
    |--------------------------------------------------------------------------
    |
    | Solo se crea cuando el correo y la contraseña están definidos. La
    | contraseña debe tener al menos 12 caracteres (ver AdminUserSeeder).
    |
    */

    'admin' => [
        'name' => env('SCITRACE_ADMIN_NAME', 'Administrador SciTrace'),
        'email' => env('SCITRACE_ADMIN_EMAIL'),
        'password' => env('SCITRACE_ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retención documental
    |--------------------------------------------------------------------------
    |
    | Valor informativo para la Fase 1. La purga automática se implementará
    | en la fase de gestión documental.
    |
    */

    'retention_days' => (int) env('SCITRACE_DOCUMENT_RETENTION_DAYS', 365),

    /*
    |--------------------------------------------------------------------------
    | Documentos
    |--------------------------------------------------------------------------
    |
    | La carga se valida en Laravel y el archivo se guarda en un disco privado.
    | Los límites del microservicio se configuran aparte con variables AI_*.
    |
    */

    'documents' => [
        'disk' => env('SCITRACE_DOCUMENTS_DISK', 'local'),
        'max_kb' => (int) env('SCITRACE_MAX_UPLOAD_KB', 25600),
        'max_segments' => (int) env('SCITRACE_MAX_SEGMENTS', 10000),
        'max_segment_chars' => (int) env('SCITRACE_MAX_SEGMENT_CHARS', 200000),
    ],

];
