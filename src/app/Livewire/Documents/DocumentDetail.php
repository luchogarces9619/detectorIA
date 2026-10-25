<?php

namespace App\Livewire\Documents;

use App\Enums\DocumentStatus;
use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class DocumentDetail extends Component
{
    public Document $document;

    public function mount(Document $document): void
    {
        $this->authorize('view', $document);
        $this->document = $document;
    }

    public function getProcessingProgressProperty(): array
    {
        $metadata = is_array($this->document->metadata) ? $this->document->metadata : [];
        $hasExtraction = isset($metadata['extraction']);
        $hasAnalysis = isset($metadata['analysis']);
        $hasPlagiarism = isset($metadata['plagiarism']);

        if ($this->document->status === DocumentStatus::Ready && $hasExtraction && $hasAnalysis && $hasPlagiarism) {
            return ['percent' => 100, 'stage' => 'Completado', 'details' => 'Extracción, detección de IA y plagio finalizados'];
        }
        if ($this->document->status === DocumentStatus::Ready && $hasExtraction && $hasAnalysis && ! $hasPlagiarism) {
            return ['percent' => 85, 'stage' => 'Detección de plagio', 'details' => 'Detección de IA completada, ejecutando plagio'];
        }
        if ($this->document->status === DocumentStatus::Ready && $hasExtraction && ! $hasAnalysis && $hasPlagiarism) {
            return ['percent' => 85, 'stage' => 'Detección de IA', 'details' => 'Plagio completado, ejecutando detección de IA'];
        }
        if ($this->document->status === DocumentStatus::Ready && $hasExtraction && ! $hasAnalysis && ! $hasPlagiarism) {
            return ['percent' => 70, 'stage' => 'Análisis', 'details' => 'Extracción completada, ejecutando análisis'];
        }
        if ($this->document->status === DocumentStatus::Processing) {
            return ['percent' => 40, 'stage' => 'Procesando', 'details' => 'Extracción y análisis en curso'];
        }
        if (in_array($this->document->status, [DocumentStatus::Uploaded, DocumentStatus::Validating], true)) {
            return ['percent' => 15, 'stage' => 'En cola', 'details' => 'Esperando procesamiento'];
        }
        if ($this->document->status === DocumentStatus::Failed) {
            return ['percent' => 0, 'stage' => 'Error', 'details' => $this->document->failure_message ?? 'Error en procesamiento'];
        }

        return ['percent' => 0, 'stage' => $this->document->status->label(), 'details' => ''];
    }

    public function reprocess(): void
    {
        $this->authorize('reprocess', $this->document);

        if (in_array($this->document->status, [
            DocumentStatus::Uploaded,
            DocumentStatus::Validating,
            DocumentStatus::Processing,
        ], true)) {
            session()->flash('document_error', 'El documento ya está en cola o en proceso.');

            return;
        }

        $this->document->forceFill([
            'status' => DocumentStatus::Uploaded,
            'failure_code' => null,
            'failure_message' => null,
        ])->save();

        ProcessDocument::dispatch($this->document->getKey());

        app(AuditLogger::class)->log('document.reprocessed', subject: $this->document);

        session()->flash('document_status', 'Documento enviado nuevamente a procesamiento.');
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->document);

        Storage::disk($this->document->storage_disk)->delete($this->document->storage_path);

        $this->document->delete();

        app(AuditLogger::class)->log('document.deleted', subject: $this->document);

        session()->flash('status', 'Documento eliminado.');

        $this->redirectRoute('documents.index', navigate: true);
    }

    public function render()
    {
        $this->authorize('view', $this->document);
        $this->document->refresh();
        $this->document->load([
            'sections',
            'segments',
            'latestAnalysis.predictions.segment',
            'latestAnalysis.modelVersion',
        ]);

        return view('livewire.documents.document-detail');
    }
}
