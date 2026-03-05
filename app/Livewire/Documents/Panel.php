<?php

namespace App\Livewire\Documents;

use App\Models\Document;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

class Panel extends Component
{
    use WithFileUploads;

    public string $documentableType;

    public int $documentableId;

    public string $title = 'Documentos';

    public $document;

    public function mount(string $documentableType, int $documentableId, string $title = 'Documentos'): void
    {
        if (! (auth()->user()?->can('documents.view') ?? false)) {
            abort(403);
        }

        $this->documentableType = $documentableType;
        $this->documentableId = $documentableId;
        $this->title = $title;
    }

    public function upload(): void
    {
        if (! (auth()->user()?->can('documents.upload') ?? false)) {
            abort(403);
        }

        $this->validate([
            'document' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ], [
            'document.required' => 'Selecciona un archivo para subir.',
            'document.max' => 'El archivo excede el limite de 5 MB.',
            'document.mimes' => 'Solo se permiten archivos JPG, PNG o PDF.',
        ]);

        $documentable = $this->resolveDocumentable();
        $disk = (string) config('filesystems.documents_disk', 'public');
        $folder = 'documents/'.strtolower(class_basename($documentable)).'/'.$documentable->getAttribute('organization_id');
        $path = $this->document->store($folder, $disk);

        try {
            Document::query()->create([
                'organization_id' => (int) $documentable->getAttribute('organization_id'),
                'documentable_type' => $this->documentableType,
                'documentable_id' => $this->documentableId,
                'path' => $path,
                'mime' => $this->document->getMimeType() ?: 'application/octet-stream',
                'size' => $this->document->getSize() ?: 0,
                'type' => strtoupper(class_basename($documentable)).'_DOCUMENT',
                'tags' => [strtolower(class_basename($documentable)), 'manual-upload'],
                'meta' => [
                    'disk' => $disk,
                    'uploaded_at' => now()->toISOString(),
                ],
            ]);
        } catch (ValidationException $exception) {
            $message = $exception->errors()['month_close'][0] ?? 'No se pudo subir el documento.';
            $this->addError('document', $message);

            return;
        }

        app(AuditLogger::class)->log(
            action: 'document.uploaded',
            auditable: $documentable,
            summary: sprintf('Documento subido en %s #%d', class_basename($documentable), $documentable->getKey()),
            meta: [
                'documentable_type' => $this->documentableType,
                'documentable_id' => $this->documentableId,
                'mime' => $this->document->getMimeType(),
            ],
        );

        $this->reset('document');
        session()->flash('success', 'Documento subido correctamente.');
    }

    public function render(): View
    {
        $documents = Document::query()
            ->where('documentable_type', $this->documentableType)
            ->where('documentable_id', $this->documentableId)
            ->latest('created_at')
            ->get()
            ->map(function (Document $document): array {
                $disk = (string) data_get($document->meta, 'disk', config('filesystems.documents_disk', 'public'));

                return [
                    'id' => $document->id,
                    'path' => $document->path,
                    'url' => Storage::disk($disk)->url($document->path),
                    'mime' => $document->mime,
                    'size' => (int) $document->size,
                    'created_at' => $document->created_at,
                ];
            });

        return view('livewire.documents.panel', [
            'documents' => $documents,
            'canUploadDocuments' => auth()->user()?->can('documents.upload') ?? false,
        ]);
    }

    private function resolveDocumentable(): Model
    {
        if (! class_exists($this->documentableType) || ! is_subclass_of($this->documentableType, Model::class)) {
            abort(404);
        }

        /** @var class-string<Model> $documentableClass */
        $documentableClass = $this->documentableType;

        return $documentableClass::query()->findOrFail($this->documentableId);
    }

    public function formatFileSize(int $size): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $value = (float) $size;
        $unitIndex = 0;

        while ($value >= 1024 && $unitIndex < count($units) - 1) {
            $value /= 1024;
            $unitIndex++;
        }

        return number_format($value, $unitIndex === 0 ? 0 : 1).' '.$units[$unitIndex];
    }
}
