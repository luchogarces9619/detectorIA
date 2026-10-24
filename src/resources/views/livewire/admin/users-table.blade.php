<div class="space-y-4">
    @if (session('admin_status'))
        <x-ui.alert type="success">{{ session('admin_status') }}</x-ui.alert>
    @endif
    @if (session('admin_error'))
        <x-ui.alert type="danger">{{ session('admin_error') }}</x-ui.alert>
    @endif

    <section class="card">
        <div class="card-body border-b border-slate-100">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_11rem_11rem]">
                <div>
                    <label for="user-search" class="label">Buscar</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex w-10 items-center justify-center text-slate-400">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                        </span>
                        <input id="user-search" type="search" wire:model.live.debounce.300ms="search" class="input pl-10" placeholder="Nombre o correo">
                    </div>
                </div>
                <div>
                    <label for="role-filter" class="label">Rol</label>
                    <select id="role-filter" wire:model.live="roleFilter" class="input">
                        <option value="">Todos</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->value }}">{{ $role->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="status-filter" class="label">Estado</label>
                    <select id="status-filter" wire:model.live="statusFilter" class="input">
                        <option value="">Todos</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        @if ($users->isEmpty())
            <x-ui.empty-state title="Sin usuarios que coincidan" description="Ajusta la búsqueda o los filtros para ver otras cuentas." />
        @else
            <div class="table-shell">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Estado</th>
                            <th>Documentos</th>
                            <th>Registro</th>
                            <th class="text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr wire:key="user-{{ $user->id }}">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-navy-100 text-xs font-bold text-navy-700">
                                            {{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-slate-900">
                                                {{ $user->name }}
                                                @if ($user->id === auth()->id())
                                                    <span class="ml-1 text-[10px] font-semibold uppercase tracking-wide text-aqua-600">Tú</span>
                                                @endif
                                            </p>
                                            <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <select
                                        class="input min-w-[9.5rem] py-1.5 text-xs"
                                        wire:change="changeRole('{{ $user->id }}', $event.target.value)"
                                        @disabled($user->id === auth()->id())
                                        aria-label="Rol de {{ $user->name }}"
                                    >
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->value }}" @selected($user->role === $role)>{{ $role->label() }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <x-ui.badge :tone="$user->status->value === 'active' ? 'success' : 'danger'">{{ $user->status->label() }}</x-ui.badge>
                                </td>
                                <td class="tabular-nums">{{ $user->documents_count }}</td>
                                <td class="text-xs text-slate-500">{{ $user->created_at?->format('d/m/Y') }}</td>
                                <td class="text-right">
                                    @if ($user->id === auth()->id())
                                        <span class="text-xs text-slate-400">—</span>
                                    @else
                                        <button
                                            type="button"
                                            class="{{ $user->status->value === 'active' ? 'btn-danger' : 'btn-secondary' }} px-3 py-1.5 text-xs"
                                            wire:click="toggleStatus('{{ $user->id }}')"
                                            wire:confirm="{{ $user->status->value === 'active' ? '¿Suspender esta cuenta? El usuario no podrá iniciar sesión.' : '¿Reactivar esta cuenta?' }}"
                                            wire:loading.attr="disabled"
                                            wire:target="toggleStatus('{{ $user->id }}')"
                                        >
                                            {{ $user->status->value === 'active' ? 'Suspender' : 'Reactivar' }}
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($users->hasPages())
                <div class="border-t border-slate-100 px-5 py-4">
                    {{ $users->links() }}
                </div>
            @endif
        @endif
    </section>

    <p class="px-1 text-xs text-slate-500">
        Mostrando {{ $users->count() }} de {{ $users->total() }} cuentas. Los cambios se registran en la auditoría.
    </p>
</div>
