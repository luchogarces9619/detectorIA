@if (session('status'))
    <x-ui.alert type="success" class="mb-6">{{ session('status') }}</x-ui.alert>
@endif

@if (session('error'))
    <x-ui.alert type="danger" class="mb-6">{{ session('error') }}</x-ui.alert>
@endif
