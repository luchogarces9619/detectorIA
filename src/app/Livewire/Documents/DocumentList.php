<?php

namespace App\Livewire\Documents;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithPagination;

class DocumentList extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    protected string $paginationTheme = 'tailwind';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function delete(string $documentId): void
    {
        $document = Document::query()->findOrFail($documentId);

        $this->authorize('delete', $document);

        Storage::disk($document->storage_disk)->delete($document->storage_path);

        $document->delete();

        app(AuditLogger::class)->log('document.deleted', subject: $document);

        session()->flash('status', 'Documento eliminado.');
    }

    public function render()
    {
        $documents = Document::query()
            ->where('user_id', Auth::id())
            ->when($this->search !== '', function ($query): void {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($this->search)).'%';
                $query->where('original_name', 'like', $term);
            })
            ->when(
                $this->statusFilter !== '',
                fn ($query) => $query->where('status', $this->statusFilter),
            )
            ->latest()
            ->paginate(10);

        return view('livewire.documents.document-list', [
            'documents' => $documents,
            'statuses' => DocumentStatus::cases(),
        ]);
    }
}
