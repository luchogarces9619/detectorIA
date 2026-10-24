<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            'auth.login' => 'Inicio de sesión',
            'auth.login_failed' => 'Intento de acceso fallido',
            'auth.logout' => 'Cierre de sesión',
            'auth.registered' => 'Registro de usuario',
            'auth.password_reset' => 'Contraseña restablecida',
            'profile.updated' => 'Perfil actualizado',
            'profile.password_changed' => 'Contraseña actualizada',
            'admin.user.status_changed' => 'Estado de usuario modificado',
            'admin.user.role_changed' => 'Rol de usuario modificado',
            default => $this->action,
        };
    }

    public function actionTone(): string
    {
        return match ($this->action) {
            'auth.login_failed' => 'warning',
            'admin.user.status_changed', 'admin.user.role_changed' => 'info',
            default => 'neutral',
        };
    }
}
