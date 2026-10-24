<x-layouts.app title="Usuarios">
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-semibold tracking-tight text-navy-950">Usuarios</h1>
            <p class="hidden text-xs text-slate-500 sm:block">Roles, estado de acceso y actividad documental</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <x-ui.alert type="info">
            Cambiar un rol o suspender una cuenta queda registrado en la auditoría. No es posible suspender ni degradar la propia cuenta.
        </x-ui.alert>

        <livewire:admin.users-table />
    </div>
</x-layouts.app>
