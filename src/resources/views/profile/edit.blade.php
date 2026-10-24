<x-layouts.app title="Mi perfil">
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-semibold tracking-tight text-navy-950">Mi perfil</h1>
            <p class="hidden text-xs text-slate-500 sm:block">Datos de cuenta y seguridad</p>
        </div>
    </x-slot>

    <div class="max-w-3xl">
        <livewire:profile.edit-profile />
    </div>
</x-layouts.app>
