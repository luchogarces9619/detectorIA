<?php

namespace App\Livewire\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class UsersTable extends Component
{
    use WithPagination;

    public string $search = '';

    public string $roleFilter = '';

    public string $statusFilter = '';

    protected string $paginationTheme = 'tailwind';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function toggleStatus(string $userId): void
    {
        $user = User::query()->findOrFail($userId);

        if ($user->getKey() === Auth::id()) {
            session()->flash('admin_error', 'No puedes suspender tu propia cuenta.');

            return;
        }

        $previous = $user->status;
        $user->status = $user->status === UserStatus::Active
            ? UserStatus::Suspended
            : UserStatus::Active;
        $user->save();

        app(AuditLogger::class)->log('admin.user.status_changed', subject: $user, metadata: [
            'from' => $previous->value,
            'to' => $user->status->value,
        ]);

        session()->flash('admin_status', 'Estado del usuario actualizado.');
    }

    public function changeRole(string $userId, string $role): void
    {
        $newRole = UserRole::tryFrom($role);

        if ($newRole === null) {
            session()->flash('admin_error', 'Rol no válido.');

            return;
        }

        $user = User::query()->findOrFail($userId);

        if ($user->getKey() === Auth::id()) {
            session()->flash('admin_error', 'No puedes cambiar tu propio rol.');

            return;
        }

        if ($newRole !== UserRole::Admin && $user->role === UserRole::Admin) {
            $adminCount = User::query()
                ->where('role', UserRole::Admin)
                ->where('status', UserStatus::Active)
                ->count();

            if ($adminCount <= 1) {
                session()->flash('admin_error', 'Debe existir al menos un administrador activo.');

                return;
            }
        }

        $previous = $user->role;
        $user->role = $newRole;
        $user->save();

        app(AuditLogger::class)->log('admin.user.role_changed', subject: $user, metadata: [
            'from' => $previous->value,
            'to' => $newRole->value,
        ]);

        session()->flash('admin_status', 'Rol del usuario actualizado.');
    }

    public function render()
    {
        $users = User::query()
            ->withCount('documents')
            ->when($this->search !== '', function ($query): void {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($this->search)).'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->when($this->roleFilter !== '', fn ($query) => $query->where('role', $this->roleFilter))
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return view('livewire.admin.users-table', [
            'users' => $users,
            'roles' => UserRole::cases(),
            'statuses' => UserStatus::cases(),
        ]);
    }
}
