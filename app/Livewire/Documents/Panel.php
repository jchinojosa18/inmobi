<?php

namespace App\Livewire\Documents;

use App\Mail\ContractDocumentMail;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Unit;
use App\Support\AuditLogger;
use App\Support\ContractDocumentCategory;
use App\Support\DateDisplay;
use App\Support\DocumentShareUrl;
use App\Support\FileViewerItem;
use App\Support\OrganizationSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithFileUploads;

class Panel extends Component
{
    use WithFileUploads;

    private const DEFAULT_MAX_FILE_KB = 5120;

    private const CONTRACT_MAX_FILE_KB = 10240;

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

    public string $variant = 'default';

    public string $category = '';

    public $document;

    public bool $showUploadModal = false;

    public int $uploadInputKey = 0;

    public bool $showDeleteConfirm = false;

    public ?int $pendingDeleteDocumentId = null;

    public bool $showShareModal = false;

    public ?int $sharingDocumentId = null;

    public ?string $shareUrl = null;

    public ?string $whatsAppUrl = null;

    public ?string $shareTenantEmail = null;

    public ?string $shareEmailFeedback = null;

    public function mount(
        string $documentableType,
        int $documentableId,
        ?string $title = null,
        string $variant = 'default',
    ): void {
        $this->authorize('viewAny', Document::class);

        $this->documentableType = $documentableType;
        $this->documentableId = $documentableId;
        $this->title = $title ?? __('documents.title');
        $this->variant = $variant;

        $this->resolveDocumentable();
    }

    public function save(): void
    {
        if (! (auth()->user()?->can('documents.upload') ?? false)) {
            abort(403);
        }

        $this->assertDocumentableAllowsMutations();

        $this->validate();

        if ($this->isContractVariant() && $this->categoryIsTaken()) {
            throw ValidationException::withMessages([
                'category' => __('contracts.document_category_taken'),
            ]);
        }

        $documentable = $this->resolveDocumentable();
        $disk = (string) config('filesystems.documents_disk', 'local');
        $folder = 'documents/'.strtolower(class_basename($documentable)).'/'.$documentable->getAttribute('organization_id');
        $path = $this->document->store($folder, $disk);

        try {
            Document::storeNew([
                'organization_id' => (int) $documentable->getAttribute('organization_id'),
                'documentable_type' => $this->documentableType,
                'documentable_id' => $this->documentableId,
                'path' => $path,
                'mime' => $this->document->getMimeType() ?: 'application/octet-stream',
                'size' => $this->document->getSize() ?: 0,
                'type' => strtoupper(class_basename($documentable)).'_DOCUMENT',
                'category' => $this->isContractVariant() ? $this->category : null,
                'tags' => [strtolower(class_basename($documentable)), 'manual-upload'],
                'meta' => [
                    'disk' => $disk,
                    'uploaded_at' => now()->toISOString(),
                ],
            ]);
        } catch (ValidationException $exception) {
            $this->reset('document', 'category');
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
                'category' => $this->isContractVariant() ? $this->category : null,
            ],
        );

        $this->reset('document', 'category');
        $this->resetUploadInput();
        $this->closeUploadModal();
        session()->flash('success', __('documents.uploaded_success'));
    }

    public function openUploadModal(): void
    {
        if (! (auth()->user()?->can('documents.upload') ?? false)) {
            abort(403);
        }

        $this->assertDocumentableAllowsMutations();

        if ($this->isContractVariant() && $this->availableContractCategories() === []) {
            return;
        }

        $this->showUploadModal = true;
    }

    public function closeUploadModal(): void
    {
        $this->showUploadModal = false;
        $this->reset('document', 'category');
        $this->resetValidation();
        $this->resetUploadInput();
    }

    private function resetUploadInput(): void
    {
        $this->uploadInputKey++;
        $this->dispatch('document-upload-reset');
    }

    public function confirmDeleteDocument(int $documentId): void
    {
        if (! (auth()->user()?->can('documents.delete') ?? false)) {
            abort(403);
        }

        $this->assertDocumentableAllowsMutations();

        $this->pendingDeleteDocumentId = $documentId;
        $this->showDeleteConfirm = true;
    }

    public function cancelDeleteDocumentConfirm(): void
    {
        $this->showDeleteConfirm = false;
        $this->pendingDeleteDocumentId = null;
    }

    public function executeDeleteDocumentConfirm(): void
    {
        if ($this->pendingDeleteDocumentId === null) {
            return;
        }

        $this->deleteDocument($this->pendingDeleteDocumentId);
        $this->cancelDeleteDocumentConfirm();
    }

    public function deleteDocument(int $documentId): void
    {
        $this->assertDocumentableAllowsMutations();

        $document = Document::query()
            ->where('documentable_type', $this->documentableType)
            ->where('documentable_id', $this->documentableId)
            ->findOrFail($documentId);

        $this->authorize('delete', $document);

        $disk = (string) data_get($document->meta, 'disk', config('filesystems.documents_disk', 'local'));

        if (Storage::disk($disk)->exists($document->path)) {
            Storage::disk($disk)->delete($document->path);
        }

        $documentable = $this->resolveDocumentable();
        $categoryValue = $document->category?->value;

        $document->update(['category' => null]);
        $document->delete();

        app(AuditLogger::class)->log(
            action: 'document.deleted',
            auditable: $documentable,
            summary: __('documents.audit_deleted', [
                'type' => class_basename($documentable),
                'id' => $documentable->getKey(),
            ]),
            meta: [
                'document_id' => $documentId,
                'category' => $categoryValue,
            ],
        );

        session()->flash('success', __('contracts.document_deleted_success'));
    }

    public function openShareModal(int $documentId): void
    {
        if (! (auth()->user()?->can('documents.view') ?? false)) {
            abort(403);
        }

        $document = $this->findShareableContractDocument($documentId);
        $contract = $this->resolveDocumentable();
        $contract->loadMissing(['tenant', 'unit.property']);

        $this->sharingDocumentId = $document->id;
        $this->shareUrl = DocumentShareUrl::make($document->id);
        $this->shareTenantEmail = $contract->tenant?->email;
        $this->shareEmailFeedback = null;
        $this->whatsAppUrl = $this->buildContractDocumentWhatsAppUrl($contract, $this->shareUrl);
        $this->showShareModal = true;
    }

    public function closeShareModal(): void
    {
        $this->showShareModal = false;
        $this->sharingDocumentId = null;
        $this->shareUrl = null;
        $this->whatsAppUrl = null;
        $this->shareTenantEmail = null;
        $this->shareEmailFeedback = null;
    }

    public function sendContractDocumentEmail(): void
    {
        if (! (auth()->user()?->can('receipts.send') ?? false)) {
            abort(403);
        }

        if ($this->sharingDocumentId === null) {
            return;
        }

        $document = $this->findShareableContractDocument($this->sharingDocumentId);
        $contract = $this->resolveDocumentable();
        $contract->loadMissing(['tenant']);
        $email = $contract->tenant?->email;

        if (! is_string($email) || $email === '') {
            $this->shareEmailFeedback = __('documents.no_tenant_email');

            return;
        }

        $disk = (string) data_get($document->meta, 'disk', config('filesystems.documents_disk', 'local'));
        if (! Storage::disk($disk)->exists($document->path)) {
            $this->shareEmailFeedback = __('documents.file_missing');

            return;
        }

        try {
            Mail::to($email)->send(new ContractDocumentMail($document));
            $this->shareEmailFeedback = __('documents.email_sent');
        } catch (\Throwable $e) {
            report($e);
            $this->shareEmailFeedback = __('documents.email_failed');
        }
    }

    protected function rules(): array
    {
        if ($this->isContractVariant()) {
            return [
                'category' => ['required', 'string', Rule::enum(ContractDocumentCategory::class)],
                'document' => ['required', 'file', 'max:'.self::CONTRACT_MAX_FILE_KB, 'mimes:pdf'],
            ];
        }

        return [
            'document' => ['required', 'file', 'max:'.self::DEFAULT_MAX_FILE_KB, 'mimes:jpg,jpeg,png,pdf'],
        ];
    }

    protected function messages(): array
    {
        $messages = [
            'document.required' => __('documents.validation.required'),
            'document.max' => __('documents.validation.max'),
        ];

        if ($this->isContractVariant()) {
            $messages['category.required'] = __('contracts.document_category_required');
            $messages['document.mimes'] = __('contracts.document_pdf_only');
            $messages['document.max'] = __('documents.validation.max_contract');

            return $messages;
        }

        $messages['document.mimes'] = __('documents.validation.mimes');

        return $messages;
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
                    'file_name' => basename($document->path),
                    'url' => route('documents.download', $document),
                    'mime' => $document->mime,
                    'size' => (int) $document->size,
                    'created_at' => $document->created_at,
                    'category' => $document->category?->value,
                    'category_label' => data_get($document->meta, 'kind') === 'deposit_refund_receipt'
                        ? __('contracts.deposit_refund_receipt_document')
                            .(data_get($document->meta, 'folio') ? ' · '.data_get($document->meta, 'folio') : '')
                        : $document->category?->label(),
                ];
            });

        return view('livewire.documents.panel', [
            'documents' => $documents,
            'viewerItems' => $documents
                ->map(fn (array $item): array => FileViewerItem::fromDocumentRoute(
                    $item['id'],
                    $item['file_name'],
                    $item['mime'],
                ))
                ->values()
                ->all(),
            'canUploadDocuments' => $this->canMutateDocuments(),
            'canDeleteDocuments' => $this->canDeleteDocuments(),
            'canSendReceipts' => auth()->user()?->can('receipts.send') ?? false,
            'variant' => $this->variant,
            'availableCategories' => $this->isContractVariant()
                ? $this->availableContractCategories()
                : [],
            'canOpenUpload' => $this->canOpenUpload(),
        ]);
    }

    private function canMutateDocuments(): bool
    {
        if (! (auth()->user()?->can('documents.upload') ?? false)) {
            return false;
        }

        return $this->documentableAllowsMutations();
    }

    private function canDeleteDocuments(): bool
    {
        if (! (auth()->user()?->can('documents.delete') ?? false)) {
            return false;
        }

        return $this->documentableAllowsMutations();
    }

    private function canOpenUpload(): bool
    {
        if (! $this->canMutateDocuments()) {
            return false;
        }

        if ($this->isContractVariant()) {
            return $this->availableContractCategories() !== [];
        }

        return true;
    }

    private function documentableAllowsMutations(): bool
    {
        $documentable = $this->resolveDocumentable();

        if ($documentable instanceof Contract) {
            return $documentable->allowsLedgerMutations();
        }

        return true;
    }

    private function assertDocumentableAllowsMutations(): void
    {
        if (! $this->documentableAllowsMutations()) {
            abort(403);
        }
    }

    private function isContractVariant(): bool
    {
        return $this->variant === 'contract'
            && $this->documentableType === Contract::class;
    }

    private function categoryIsTaken(): bool
    {
        return Document::query()
            ->where('documentable_type', $this->documentableType)
            ->where('documentable_id', $this->documentableId)
            ->where('category', $this->category)
            ->exists();
    }

    /**
     * @return array<string, string>
     */
    private function availableContractCategories(): array
    {
        $used = Document::query()
            ->where('documentable_type', $this->documentableType)
            ->where('documentable_id', $this->documentableId)
            ->whereNotNull('category')
            ->pluck('category')
            ->map(fn ($value) => $value instanceof ContractDocumentCategory ? $value->value : (string) $value)
            ->all();

        return array_diff_key(ContractDocumentCategory::options(), array_flip($used));
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

    private function findShareableContractDocument(int $documentId): Document
    {
        if (! $this->isContractVariant()) {
            abort(404);
        }

        $document = Document::query()
            ->where('documentable_type', Contract::class)
            ->where('documentable_id', $this->documentableId)
            ->findOrFail($documentId);

        if ($document->category !== ContractDocumentCategory::Contract) {
            abort(403);
        }

        return $document;
    }

    private function buildContractDocumentWhatsAppUrl(Contract $contract, string $shareUrl): string
    {
        $settingsService = app(OrganizationSettingsService::class);
        $settings = $settingsService->forOrganization((int) $contract->organization_id);
        $unitName = trim((string) ($contract->unit?->property?->name.' / '.$contract->unit?->name));
        $phone = preg_replace('/\D+/', '', (string) $contract->tenant?->phone) ?: null;

        $message = $settingsService->renderTemplate(
            (string) $settings['contract_whatsapp_template'],
            [
                'tenant_name' => (string) ($contract->tenant?->full_name ?? 'cliente'),
                'unit_name' => $unitName !== '' ? $unitName : 'unidad',
                'shared_contract_url' => $shareUrl,
                'rent_amount' => (float) $contract->rent_amount,
                'starts_at' => DateDisplay::formatDate($contract->starts_at),
                'ends_at' => DateDisplay::formatDate($contract->ends_at),
            ]
        );

        $encoded = rawurlencode($message);

        return $phone !== null
            ? "https://wa.me/{$phone}?text={$encoded}"
            : "https://wa.me/?text={$encoded}";
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
