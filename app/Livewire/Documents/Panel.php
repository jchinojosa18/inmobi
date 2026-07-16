<?php

namespace App\Livewire\Documents;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Unit;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

class Panel extends Component
{
    use WithFileUploads;

    /**
     * @var list<class-string<Model>>
     */
    private const ALLOWED_DOCUMENTABLE_TYPES = [
        Contract::class,
        Payment::class,
        Expense::class,
        Unit::class,
        Charge::class,
    ];

    public string $documentableType;

    public int $documentableId;

    public string $title = '';

    public $document;

    public function mount(string $documentableType, int $documentableId, ?string $title = null): void
    {
        if (! (auth()->user()?->can('documents.view') ?? false)) {
            abort(403);
        }

        $this->documentableType = $documentableType;
        $this->documentableId = $documentableId;
        $this->title = $title ?? __('documents.title');

        $this->resolveDocumentable();
    }

    public function save(): void
    {
        if (! (auth()->user()?->can('documents.upload') ?? false)) {
            abort(403);
        }

        $this->validate();

        $documentable = $this->resolveDocumentable();
        $disk = (string) config('filesystems.documents_disk', 'local');
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
            $this->reset('document');
            throw $exception;
        }

        app(AuditLogger::class)->log(
            action: 'document.uploaded',
            auditable: $documentable,
            summary: __('documents.audit_uploaded', [
                'type' => class_basename($documentable),
                'id' => $documentable->getKey(),
            ]),
            meta: [
                'documentable_type' => $this->documentableType,
                'documentable_id' => $this->documentableId,
                'mime' => $this->document->getMimeType(),
            ],
        );

        $this->reset('document');
        session()->flash('success', __('documents.uploaded_success'));
    }

    protected function rules(): array
    {
        return [
            'document' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ];
    }

    protected function messages(): array
    {
        return [
            'document.required' => __('documents.validation.required'),
            'document.max' => __('documents.validation.max'),
            'document.mimes' => __('documents.validation.mimes'),
        ];
    }

    public function render(): View
    {
        $documents = Document::query()
            ->where('documentable_type', $this->documentableType)
            ->where('documentable_id', $this->documentableId)
            ->latest('created_at')
            ->get()
            ->map(function (Document $document): array {
                return [
                    'id' => $document->id,
                    'path' => $document->path,
                    'url' => route('documents.download', $document),
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
        if (! in_array($this->documentableType, self::ALLOWED_DOCUMENTABLE_TYPES, true)) {
            abort(404);
        }

        /** @var class-string<Model> $documentableClass */
        $documentableClass = $this->documentableType;

        /** @var Model $model */
        $model = $documentableClass::query()->withoutOrganizationScope()->findOrFail($this->documentableId);

        $organizationId = (int) auth()->user()?->organization_id;

        if ((int) $model->getAttribute('organization_id') !== $organizationId) {
            abort(403);
        }

        return $model;
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
