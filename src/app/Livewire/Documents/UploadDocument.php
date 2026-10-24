<?php

namespace App\Livewire\Documents;

use App\Enums\DocumentStatus;
use App\Jobs\ProcessDocument;
use App\Models\Document;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

class UploadDocument extends Component
{
    use WithFileUploads;

    public $file = null;

    public bool $consent = false;

    public function updatedFile(): void
    {
        $this->resetValidation('file');
    }

    public function save(): void
    {
        $user = Auth::user();
        $maxKb = (int) config('scitrace.documents.max_kb', 25600);

        $this->validate([
            'file' => ['required', 'file', 'max:'.$maxKb, 'mimes:docx,pdf'],
            'consent' => ['accepted'],
        ], [
            'file.required' => 'Selecciona un archivo DOCX o PDF.',
            'file.file' => 'El archivo cargado no es válido.',
            'file.max' => 'El archivo supera el tamaño máximo permitido.',
            'file.mimes' => 'Solo se admiten documentos DOCX o PDF.',
            'consent.accepted' => 'Debes confirmar que tienes autorización para procesar el documento.',
        ], [
            'file' => 'archivo',
            'consent' => 'autorización',
        ]);

        $extension = strtolower($this->file->getClientOriginalExtension());

        if (! in_array($extension, ['docx', 'pdf'], true)) {
            $this->addError('file', 'Solo se admiten documentos DOCX o PDF.');

            return;
        }

        $realPath = $this->file->getRealPath();

        if (! is_string($realPath) || ! $this->hasValidSignature($realPath, $extension)) {
            $this->addError('file', 'El contenido del archivo no corresponde a un DOCX o PDF válido.');

            return;
        }

        $size = (int) $this->file->getSize();
        $sha256 = hash_file('sha256', $realPath);

        if (! is_string($sha256)) {
            $this->addError('file', 'No se pudo verificar la integridad del archivo.');

            return;
        }

        $diskName = (string) config('scitrace.documents.disk', 'local');
        $path = "documents/{$user->getKey()}/".Str::uuid().'.'.$extension;

        try {
            $storedPath = $this->file->storeAs(
                "documents/{$user->getKey()}",
                basename($path),
                $diskName,
            );
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('file', 'No se pudo almacenar el archivo. Inténtalo nuevamente.');

            return;
        }

        if (! is_string($storedPath) || $storedPath === '') {
            $this->addError('file', 'No se pudo almacenar el archivo. Inténtalo nuevamente.');

            return;
        }

        try {
            $document = Document::create([
                'user_id' => $user->getKey(),
                'original_name' => mb_substr($this->file->getClientOriginalName(), 0, 255),
                'storage_disk' => $diskName,
                'storage_path' => $storedPath,
                'mime_type' => $this->resolveMimeType($extension),
                'extension' => $extension,
                'size_bytes' => $size,
                'sha256' => $sha256,
                'status' => DocumentStatus::Uploaded,
                'uploaded_at' => now(),
                'metadata' => [
                    'source' => 'web',
                    'consent' => true,
                    'consent_at' => now()->toIso8601String(),
                ],
            ]);
        } catch (Throwable $exception) {
            Storage::disk($diskName)->delete($storedPath);
            report($exception);
            $this->addError('file', 'No se pudo registrar el documento. Inténtalo nuevamente.');

            return;
        }

        app(AuditLogger::class)->log('document.uploaded', subject: $document, metadata: [
            'extension' => $extension,
            'size_bytes' => $size,
        ]);

        ProcessDocument::dispatch($document->getKey());

        $this->reset('file', 'consent');

        session()->flash('status', 'Documento cargado. El procesamiento se ejecutará en segundo plano.');

        $this->redirectRoute('documents.show', ['document' => $document->getKey()], navigate: true);
    }

    public function render()
    {
        return view('livewire.documents.upload-document', [
            'maxKb' => (int) config('scitrace.documents.max_kb', 25600),
        ]);
    }

    private function hasValidSignature(string $path, string $extension): bool
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $header = (string) fread($handle, 1024);
        fclose($handle);

        return $extension === 'pdf'
            ? str_contains($header, '%PDF-')
            : str_starts_with($header, "PK\x03\x04");
    }

    private function resolveMimeType(string $extension): string
    {
        $mime = $this->file->getMimeType();

        if (is_string($mime) && $mime !== '') {
            return mb_substr($mime, 0, 150);
        }

        return $extension === 'pdf'
            ? 'application/pdf'
            : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    }
}
