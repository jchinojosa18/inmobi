# Contract Document Categories Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let users upload named-category PDF documents on contracts (Contrato, Aval, etc.), one per category, with delete-before-reupload, without changing the generic documents panel elsewhere.

**Architecture:** Add nullable `category` column on `documents` with a unique index per contract; introduce `ContractDocumentCategory` backed enum; extend `Documents\Panel` with `variant=contract` for category select, PDF-only validation, uniqueness check, and delete action using `documents.upload` permission.

**Tech Stack:** Laravel 11, Livewire 4, Tailwind, Spatie Permission, Sail for artisan/test/pint.

## Global Constraints

- Run all commands via `./vendor/bin/sail` (never bare `php artisan`).
- Multi-tenant: validate `organization_id` on mount (existing `resolveDocumentable()`).
- Contract categories (exact slugs): `contract`, `guarantor`, `id`, `address_proof`, `payslip`, `bank_statements`, `commercial_references`.
- Contract uploads: PDF only (`mimes:pdf`), max 5 MB (5120 KB).
- One document per category per contract; delete required before re-upload.
- Delete permission: `documents.upload` (not `documents.delete`).
- Generic panel (non-contract): unchanged — JPG, PNG, PDF, no category, no delete button.
- `MonthCloseGuard` applies on create/delete (already on `Document` model).
- Tests: `./vendor/bin/sail test --filter=...`; format: `./vendor/bin/sail pint --dirty`.
- Spec: `docs/superpowers/specs/2026-07-22-contract-document-categories-design.md`.

---

## File Map

| File | Responsibility |
|------|----------------|
| `database/migrations/2026_07_22_000001_add_category_to_documents_table.php` | `category` column + unique index |
| `app/Support/ContractDocumentCategory.php` | Backed enum + `options()` |
| `app/Models/Document.php` | `category` fillable, cast, auditable |
| `database/factories/DocumentFactory.php` | Optional `category` state |
| `app/Livewire/Documents/Panel.php` | `variant`, `category`, conditional rules, `deleteDocument()` |
| `resources/views/livewire/documents/panel.blade.php` | Contract vs default UI |
| `resources/views/livewire/contracts/show.blade.php` | Pass `variant="contract"` |
| `lang/es/contracts.php`, `lang/en/contracts.php` | Category labels + messages |
| `lang/es/documents.php`, `lang/en/documents.php` | `allowed_types_contract`, delete copy |
| `tests/Feature/Contracts/ContractDocumentsPanelTest.php` | Contract variant tests |
| `tests/Feature/Documents/DocumentPanelGenericTest.php` | Regression: generic panel unchanged |

---

### Task 1: Migration, enum, and Document model

**Files:**
- Create: `database/migrations/2026_07_22_000001_add_category_to_documents_table.php`
- Create: `app/Support/ContractDocumentCategory.php`
- Modify: `app/Models/Document.php`
- Modify: `database/factories/DocumentFactory.php`
- Test: `tests/Unit/Support/ContractDocumentCategoryTest.php`

**Interfaces:**
- Produces: `ContractDocumentCategory` enum with `options(): array<string, string>`
- Produces: `documents.category` column (nullable string)
- Produces: `Document` with `category` cast to enum when set

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Support;

use App\Support\ContractDocumentCategory;
use Tests\TestCase;

class ContractDocumentCategoryTest extends TestCase
{
    public function test_options_returns_all_seven_categories(): void
    {
        $options = ContractDocumentCategory::options();

        $this->assertCount(7, $options);
        $this->assertArrayHasKey('contract', $options);
        $this->assertArrayHasKey('commercial_references', $options);
        $this->assertSame(__('contracts.document_categories.contract'), $options['contract']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail test --filter=ContractDocumentCategoryTest`
Expected: FAIL — class `ContractDocumentCategory` not found

- [ ] **Step 3: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->string('category', 50)->nullable()->after('type');

            $table->unique(
                ['organization_id', 'documentable_type', 'documentable_id', 'category'],
                'uniq_docs_org_docable_category'
            );
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropUnique('uniq_docs_org_docable_category');
            $table->dropColumn('category');
        });
    }
};
```

Run: `./vendor/bin/sail artisan migrate`

- [ ] **Step 4: Create enum**

```php
<?php

namespace App\Support;

enum ContractDocumentCategory: string
{
    case Contract = 'contract';
    case Guarantor = 'guarantor';
    case Id = 'id';
    case AddressProof = 'address_proof';
    case Payslip = 'payslip';
    case BankStatements = 'bank_statements';
    case CommercialReferences = 'commercial_references';

    public function label(): string
    {
        return __('contracts.document_categories.'.$this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
```

- [ ] **Step 5: Update Document model**

In `app/Models/Document.php`:
- Add `use App\Support\ContractDocumentCategory;`
- Add `'category'` to `$fillable` and `auditableAttributes()`
- Add cast: `'category' => ContractDocumentCategory::class` (nullable enum cast works when null)

- [ ] **Step 6: Add translation stubs** (minimal for unit test)

In `lang/es/contracts.php` and `lang/en/contracts.php`, add:

```php
'document_categories' => [
    'contract' => 'Contrato', // EN: 'Contract'
    'guarantor' => 'Aval', // EN: 'Guarantor'
    'id' => 'Identificación oficial', // EN: 'Official ID'
    'address_proof' => 'Comprobante de domicilio', // EN: 'Proof of address'
    'payslip' => 'Recibo de nómina', // EN: 'Pay slip'
    'bank_statements' => 'Estados de cuenta', // EN: 'Bank statements'
    'commercial_references' => 'Referencias comerciales', // EN: 'Commercial references'
],
```

- [ ] **Step 7: Update DocumentFactory**

Add optional `'category' => null` to definition (default null). Add state:

```php
public function forContractCategory(ContractDocumentCategory $category): static
{
    return $this->state(fn (): array => [
        'category' => $category->value,
        'documentable_type' => Contract::class,
        'type' => 'CONTRACT_DOCUMENT',
        'mime' => 'application/pdf',
    ]);
}
```

(import `Contract` and `ContractDocumentCategory`)

- [ ] **Step 8: Run test to verify it passes**

Run: `./vendor/bin/sail test --filter=ContractDocumentCategoryTest`
Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_07_22_000001_add_category_to_documents_table.php \
  app/Support/ContractDocumentCategory.php app/Models/Document.php \
  database/factories/DocumentFactory.php lang/es/contracts.php lang/en/contracts.php \
  tests/Unit/Support/ContractDocumentCategoryTest.php
git commit -m "feat: add contract document category column and enum"
```

---

### Task 2: Contract upload — validation and persistence

**Files:**
- Modify: `app/Livewire/Documents/Panel.php`
- Create: `tests/Feature/Contracts/ContractDocumentsPanelTest.php` (upload tests first)
- Modify: `lang/es/contracts.php`, `lang/en/contracts.php` (validation messages)
- Modify: `lang/es/documents.php`, `lang/en/documents.php`

**Interfaces:**
- Consumes: `ContractDocumentCategory::options()`, `Document.category`
- Produces: `Panel` public props `$variant = 'default'`, `$category = ''`
- Produces: `Panel::save()` persists `category` when `variant === 'contract'`

- [ ] **Step 1: Write failing upload tests**

Create `tests/Feature/Contracts/ContractDocumentsPanelTest.php`:

```php
<?php

namespace Tests\Feature\Contracts;

use App\Livewire\Documents\Panel;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\ContractDocumentCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContractDocumentsPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploads_pdf_with_category(): void
    {
        Storage::fake('local');
        [$organization, $contract] = $this->createContractGraph();
        $user = User::factory()->create(['organization_id' => $organization->id]);

        Livewire::actingAs($user)
            ->test(Panel::class, [
                'documentableType' => Contract::class,
                'documentableId' => $contract->id,
                'variant' => 'contract',
            ])
            ->set('category', ContractDocumentCategory::Contract->value)
            ->set('document', UploadedFile::fake()->create('contrato.pdf', 100, 'application/pdf'))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSessionHas('success');

        $document = Document::query()->first();
        $this->assertNotNull($document);
        $this->assertSame(ContractDocumentCategory::Contract, $document->category);
        $this->assertSame('application/pdf', $document->mime);
    }

    public function test_rejects_non_pdf_on_contract_variant(): void
    {
        Storage::fake('local');
        [$organization, $contract] = $this->createContractGraph();
        $user = User::factory()->create(['organization_id' => $organization->id]);

        Livewire::actingAs($user)
            ->test(Panel::class, [
                'documentableType' => Contract::class,
                'documentableId' => $contract->id,
                'variant' => 'contract',
            ])
            ->set('category', ContractDocumentCategory::Guarantor->value)
            ->set('document', UploadedFile::fake()->image('aval.jpg'))
            ->call('save')
            ->assertHasErrors(['document']);
    }

    public function test_rejects_duplicate_category(): void
    {
        Storage::fake('local');
        [$organization, $contract] = $this->createContractGraph();
        $user = User::factory()->create(['organization_id' => $organization->id]);

        Document::factory()->create([
            'organization_id' => $organization->id,
            'documentable_type' => Contract::class,
            'documentable_id' => $contract->id,
            'category' => ContractDocumentCategory::Contract->value,
            'type' => 'CONTRACT_DOCUMENT',
            'mime' => 'application/pdf',
            'path' => 'documents/contract/'.$organization->id.'/existing.pdf',
            'meta' => ['disk' => 'local'],
        ]);

        Livewire::actingAs($user)
            ->test(Panel::class, [
                'documentableType' => Contract::class,
                'documentableId' => $contract->id,
                'variant' => 'contract',
            ])
            ->set('category', ContractDocumentCategory::Contract->value)
            ->set('document', UploadedFile::fake()->create('otro.pdf', 100, 'application/pdf'))
            ->call('save')
            ->assertHasErrors(['category']);
    }

    /**
     * @return array{Organization, Contract}
     */
    private function createContractGraph(): array
    {
        $organization = Organization::factory()->create();
        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $tenant = Tenant::factory()->create(['organization_id' => $organization->id]);
        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
        ]);

        return [$organization, $contract];
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/sail test --filter=ContractDocumentsPanelTest`
Expected: FAIL — unknown `variant` / `category` / validation not enforced

- [ ] **Step 3: Implement Panel upload logic**

In `app/Livewire/Documents/Panel.php`:

1. Add properties:
```php
public string $variant = 'default';

public string $category = '';
```

2. Update `mount()` signature:
```php
public function mount(string $documentableType, int $documentableId, ?string $title = null, string $variant = 'default'): void
```
Set `$this->variant = $variant;`

3. Replace `rules()` with conditional rules:
```php
protected function rules(): array
{
    if ($this->isContractVariant()) {
        return [
            'category' => ['required', 'string', Rule::enum(ContractDocumentCategory::class)],
            'document' => ['required', 'file', 'max:5120', 'mimes:pdf'],
        ];
    }

    return [
        'document' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
    ];
}
```

4. Add helper:
```php
private function isContractVariant(): bool
{
    return $this->variant === 'contract'
        && $this->documentableType === Contract::class;
}
```

5. In `save()`, before create, when contract variant:
```php
$categoryTaken = Document::query()
    ->where('documentable_type', $this->documentableType)
    ->where('documentable_id', $this->documentableId)
    ->where('category', $this->category)
    ->exists();

if ($categoryTaken) {
    throw ValidationException::withMessages([
        'category' => __('contracts.document_category_taken'),
    ]);
}
```

6. Pass `'category' => $this->isContractVariant() ? $this->category : null` in `Document::create([...])`.

7. Reset category on success: `$this->reset('document', 'category');`

8. Add `use Illuminate\Validation\Rule;` and `use App\Support\ContractDocumentCategory;`

9. In `render()`, map documents with `category_label`:
```php
'category' => $document->category?->value,
'category_label' => $document->category?->label(),
```

10. Pass `availableCategories` to view when contract variant:
```php
'availableCategories' => $this->isContractVariant()
    ? $this->availableContractCategories()
    : [],
```

11. Add method:
```php
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
```

- [ ] **Step 4: Add validation translation keys**

`lang/es/contracts.php`:
```php
'document_category' => 'Tipo de documento',
'document_category_required' => 'Selecciona el tipo de documento.',
'document_category_taken' => 'Ya existe un documento de este tipo. Elimínalo antes de subir otro.',
'document_pdf_only' => 'Solo se permiten archivos PDF.',
```

`lang/en/contracts.php`: English equivalents.

`lang/es/documents.php`:
```php
'allowed_types_contract' => 'Permitidos: PDF. Máximo 5 MB.',
'validation' => [
    // existing...
    'category_required' => 'Selecciona el tipo de documento.',
    'pdf_only' => 'Solo se permiten archivos PDF.',
],
```

Mirror in `lang/en/documents.php`.

Update `messages()` in Panel to map contract-specific keys.

- [ ] **Step 5: Run tests to verify they pass**

Run: `./vendor/bin/sail test --filter=ContractDocumentsPanelTest`
Expected: PASS (upload tests only; delete/UI not yet)

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Documents/Panel.php tests/Feature/Contracts/ContractDocumentsPanelTest.php \
  lang/es/contracts.php lang/en/contracts.php lang/es/documents.php lang/en/documents.php
git commit -m "feat: contract document upload with category and PDF-only validation"
```

---

### Task 3: Delete document and contract UI

**Files:**
- Modify: `app/Livewire/Documents/Panel.php`
- Modify: `resources/views/livewire/documents/panel.blade.php`
- Modify: `resources/views/livewire/contracts/show.blade.php`
- Extend: `tests/Feature/Contracts/ContractDocumentsPanelTest.php`

**Interfaces:**
- Produces: `Panel::deleteDocument(int $documentId): void`
- Produces: Blade contract table with category name + delete button

- [ ] **Step 1: Write failing delete tests**

Append to `ContractDocumentsPanelTest.php`:

```php
public function test_delete_frees_category_for_reupload(): void
{
    Storage::fake('local');
    [$organization, $contract] = $this->createContractGraph();
    $user = User::factory()->create(['organization_id' => $organization->id]);

    $path = 'documents/contract/'.$organization->id.'/contrato.pdf';
    Storage::disk('local')->put($path, 'pdf');

    $document = Document::factory()->create([
        'organization_id' => $organization->id,
        'documentable_type' => Contract::class,
        'documentable_id' => $contract->id,
        'category' => ContractDocumentCategory::Contract->value,
        'type' => 'CONTRACT_DOCUMENT',
        'mime' => 'application/pdf',
        'path' => $path,
        'meta' => ['disk' => 'local'],
    ]);

    Livewire::actingAs($user)
        ->test(Panel::class, [
            'documentableType' => Contract::class,
            'documentableId' => $contract->id,
            'variant' => 'contract',
        ])
        ->call('deleteDocument', $document->id)
        ->assertSessionHas('success');

    $this->assertSoftDeleted('documents', ['id' => $document->id]);
    Storage::disk('local')->assertMissing($path);

    Livewire::actingAs($user)
        ->test(Panel::class, [
            'documentableType' => Contract::class,
            'documentableId' => $contract->id,
            'variant' => 'contract',
        ])
        ->set('category', ContractDocumentCategory::Contract->value)
        ->set('document', UploadedFile::fake()->create('nuevo.pdf', 100, 'application/pdf'))
        ->call('save')
        ->assertHasNoErrors();
}

public function test_delete_requires_upload_permission(): void
{
    Storage::fake('local');
    [$organization, $contract] = $this->createContractGraph();

    $role = Role::findOrCreate('ViewOnlyDocs', 'web');
    $role->syncPermissions(['dashboard.view', 'documents.view']);

    $user = User::factory()->create(['organization_id' => $organization->id]);
    $user->syncRoles(['ViewOnlyDocs']);

    $document = Document::factory()->create([
        'organization_id' => $organization->id,
        'documentable_type' => Contract::class,
        'documentable_id' => $contract->id,
        'category' => ContractDocumentCategory::Contract->value,
        'path' => 'documents/contract/'.$organization->id.'/x.pdf',
        'meta' => ['disk' => 'local'],
    ]);

    Livewire::actingAs($user)
        ->test(Panel::class, [
            'documentableType' => Contract::class,
            'documentableId' => $contract->id,
            'variant' => 'contract',
        ])
        ->call('deleteDocument', $document->id)
        ->assertStatus(403);
}

public function test_used_categories_hidden_from_select(): void
{
    [$organization, $contract] = $this->createContractGraph();
    $user = User::factory()->create(['organization_id' => $organization->id]);

    Document::factory()->create([
        'organization_id' => $organization->id,
        'documentable_type' => Contract::class,
        'documentable_id' => $contract->id,
        'category' => ContractDocumentCategory::Contract->value,
    ]);

    Livewire::actingAs($user)
        ->test(Panel::class, [
            'documentableType' => Contract::class,
            'documentableId' => $contract->id,
            'variant' => 'contract',
        ])
        ->assertViewHas('availableCategories', fn (array $options): bool => ! array_key_exists('contract', $options)
            && array_key_exists('guarantor', $options));
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/sail test --filter=ContractDocumentsPanelTest`
Expected: FAIL — `deleteDocument` not found / view missing `availableCategories`

- [ ] **Step 3: Implement `deleteDocument()`**

Follow `InventoryPanel::deletePhoto()` pattern but use `documents.upload` permission:

```php
public function deleteDocument(int $documentId): void
{
    if (! (auth()->user()?->can('documents.upload') ?? false)) {
        abort(403);
    }

    $document = Document::query()
        ->where('documentable_type', $this->documentableType)
        ->where('documentable_id', $this->documentableId)
        ->findOrFail($documentId);

    $disk = (string) data_get($document->meta, 'disk', config('filesystems.documents_disk', 'local'));

    if (Storage::disk($disk)->exists($document->path)) {
        Storage::disk($disk)->delete($document->path);
    }

    $documentable = $this->resolveDocumentable();
    $categoryValue = $document->category?->value;

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
```

Add `use Illuminate\Support\Facades\Storage;`

Add `lang/*/documents.php`: `'audit_deleted' => 'Documento eliminado de :type #:id'`

Add `lang/*/contracts.php`: `'document_deleted_success' => 'Documento eliminado correctamente.'`, `'delete_document' => 'Eliminar'`, `'delete_document_confirm' => '¿Eliminar este documento?'`

- [ ] **Step 4: Update blade — contract variant UI**

In `resources/views/livewire/documents/panel.blade.php`:

- Wrap table headers: if contract variant, show `Nombre` column first; hide `Tipo` (mime) column or keep size/date.
- Show `$item['category_label']` in name column; link uses basename of path for file column.
- In upload form when `$variant === 'contract'`:
  - Category `<select wire:model="category">` with `@foreach ($availableCategories as $value => $label)`
  - `accept=".pdf"` only
  - Help text `__('documents.allowed_types_contract')`
- Add delete button per row when `$canUploadDocuments && $variant === 'contract'`:
  ```blade
  wire:click="deleteDocument({{ $item['id'] }})"
  wire:confirm="{{ __('contracts.delete_document_confirm') }}"
  ```

Pass `$variant` from `render()`:
```php
'variant' => $this->variant,
```

- [ ] **Step 5: Wire contract show page**

In `resources/views/livewire/contracts/show.blade.php`, add to existing panel tag:
```blade
variant="contract"
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `./vendor/bin/sail test --filter=ContractDocumentsPanelTest`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/Documents/Panel.php resources/views/livewire/documents/panel.blade.php \
  resources/views/livewire/contracts/show.blade.php tests/Feature/Contracts/ContractDocumentsPanelTest.php \
  lang/es/contracts.php lang/en/contracts.php lang/es/documents.php lang/en/documents.php
git commit -m "feat: contract document delete and category UI on panel"
```

---

### Task 4: Generic panel regression test and final verification

**Files:**
- Create: `tests/Feature/Documents/DocumentPanelGenericTest.php`

**Interfaces:**
- Consumes: unchanged default `Panel` behavior

- [ ] **Step 1: Write regression test**

```php
<?php

namespace Tests\Feature\Documents;

use App\Livewire\Documents\Panel;
use App\Models\Organization;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentPanelGenericTest extends TestCase
{
    use RefreshDatabase;

    public function test_unit_panel_accepts_jpeg_without_category(): void
    {
        Storage::fake('local');
        $organization = Organization::factory()->create();
        $unit = Unit::factory()->create(['organization_id' => $organization->id]);
        $user = User::factory()->create(['organization_id' => $organization->id]);

        Livewire::actingAs($user)
            ->test(Panel::class, [
                'documentableType' => Unit::class,
                'documentableId' => $unit->id,
            ])
            ->set('document', UploadedFile::fake()->image('foto.jpg'))
            ->call('save')
            ->assertHasNoErrors()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('documents', [
            'documentable_type' => Unit::class,
            'documentable_id' => $unit->id,
            'category' => null,
        ]);
    }
}
```

- [ ] **Step 2: Run full related test suite**

Run:
```bash
./vendor/bin/sail test --filter=ContractDocumentsPanelTest
./vendor/bin/sail test --filter=DocumentPanelGenericTest
./vendor/bin/sail test --filter=DocumentSecurityTest
./vendor/bin/sail pint --dirty
```

Expected: all PASS, pint clean

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Documents/DocumentPanelGenericTest.php
git commit -m "test: ensure generic document panel behavior unchanged"
```

---

## Spec Coverage Checklist

| Spec requirement | Task |
|------------------|------|
| 7 predefined categories | Task 1 enum |
| PDF only for contracts | Task 2 rules |
| One per category | Task 2 uniqueness + DB index |
| Delete with `documents.upload` | Task 3 |
| Category select excludes used | Task 2 `availableContractCategories()` |
| Generic panel unchanged | Task 4 |
| MonthCloseGuard | Existing model hooks (no change) |
| Translations | Tasks 1–3 |
| Audit on delete | Task 3 |

## Self-Review Notes

- Unique index handles race conditions; Livewire validation provides user-friendly message first.
- Existing contract documents without `category` remain listable (show path/mime); they do not block new categorized uploads.
- `documents.delete` permission intentionally unused per spec; only `documents.upload` gates delete.
