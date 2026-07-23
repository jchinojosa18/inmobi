# Tenant INE clave de elector — Design

**Date:** 2026-07-23  
**Status:** Approved for implementation

## Goal

Capture an optional Mexican INE **clave de elector** when creating or editing a tenant, show it on the tenant kardex profile, and enforce uniqueness per organization when present.

## Decisions

| Topic | Choice |
|---|---|
| Identifier | Clave de elector (18 chars), not CIC/OCR |
| Required | Optional (nullable) |
| Uniqueness | Unique per `organization_id` when not null |
| Visibility | Create/edit forms + kardex profile block |
| Out of scope | Index list column, search by INE, document upload |

## Data model

Add nullable column on `tenants`:

- `ine_clave` — `string(18)`, nullable
- Unique index: `(organization_id, ine_clave)`
- Multiple `NULL` values allowed (MySQL unique index behavior)

Model updates (`App\Models\Tenant`):

- Add `ine_clave` to `$fillable`
- Add `ine_clave` to auditable attributes

Factory: optional `ine_clave` (null by default or occasional fake 18-char uppercase alphanumeric).

## Normalization & validation

On save (create and edit):

1. Trim whitespace
2. Uppercase
3. Empty string → `null`
4. If not null:
   - Must match `/^[A-Z0-9]{18}$/` (exactly 18 alphanumeric after normalization)
   - Must be unique within the organization via `Rule::unique('tenants', 'ine_clave')->where('organization_id', …)->ignore($editingId)` (create: no ignore)

Uniqueness matches the DB unique index: soft-deleted tenants still occupy their `ine_clave` while the row exists. Reuse after soft-delete is out of scope for this change.

Labels/messages in `lang/{es,en}/catalog.php` (e.g. label “Clave de elector (INE)”, validation for format/unique).

## UI

### Forms (`Tenants\Index` create/edit modal, `Tenants\Show` edit modal)

- New optional input wired to `ine_clave`
- Place near phone/email in the existing 2-column grid
- No asterisk (optional)
- Show validation errors like other fields

### Kardex profile (`livewire/tenants/show.blade.php`)

- Add a definition row in the profile card for clave de elector
- Value: `$tenant->ine_clave ?: __('common.n_a')` (same empty pattern as phone)

### Not in this change

- Tenants index table column
- Search/filter by `ine_clave`

## Components touched

| Layer | Files |
|---|---|
| Migration | New migration adding `ine_clave` + unique index |
| Model / factory | `Tenant`, `TenantFactory` |
| Livewire | `Tenants\Index`, `Tenants\Show` |
| Views | `livewire/tenants/index.blade.php`, `livewire/tenants/show.blade.php` |
| i18n | `lang/es/catalog.php`, `lang/en/catalog.php` (and `common` if label shared) |
| Tests | Feature tests for create/edit validation, uniqueness, kardex display |

## Error handling

- Invalid format/length → field error, form stays open
- Duplicate in same org → field error citing uniqueness
- Cross-org duplicate → allowed (scoped uniqueness only)

## Testing

1. Create tenant without `ine_clave` → succeeds, DB null
2. Create with valid clave → stored uppercase, appears on kardex profile
3. Create with invalid clave (wrong length / non-alphanumeric) → validation error
4. Second tenant same org same clave → validation error
5. Edit tenant: clear clave → null; change to another unique valid clave → OK
6. Same clave in a different organization → allowed

## Non-goals

- Strict INE checksum / state-code validation beyond length + alphanumeric
- Making the field required later (can be a follow-up)
- Uploading INE image/PDF (documents module already covers files)
