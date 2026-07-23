# Unified File Viewer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Global in-page file viewer (overlay modal) for documents and PDFs, replacing `target="_blank"` on internal files.

**Architecture:** Alpine `open-file-viewer` event + `x-ui.file-viewer` in `app.blade.php`; `FileViewerItem` builds `{label, viewUrl, downloadUrl, mime, kind}`; PDF controllers honor `?inline=1`.

**Tech Stack:** Laravel 11, Livewire 4, Alpine 3, Tailwind, DomPDF

## Global Constraints

- Sail for tests: `./vendor/bin/sail test`
- No delete in viewer; no `wire:confirm`
- PDF `viewUrl` uses `?inline=1`; `downloadUrl` without inline
- Share URLs (`payments.receipt.share`) stay `target="_blank"` (out of scope)

---

### Task 1: FileViewerItem + PDF inline streaming

**Files:**
- Create: `app/Support/FileViewerItem.php`
- Create: `app/Support/StreamsPdfResponse.php`
- Modify: `app/Http/Controllers/PaymentReceiptPdfController.php`
- Modify: `app/Http/Controllers/DepositReceiptPdfController.php`
- Modify: `app/Http/Controllers/ContractSettlementPdfController.php`
- Test: `tests/Unit/Support/FileViewerItemTest.php`
- Test: `tests/Feature/FileViewer/PdfInlineDispositionTest.php`

### Task 2: UI components + layout

**Files:**
- Create: `resources/views/components/ui/file-viewer.blade.php`
- Create: `resources/views/components/ui/file-viewer-trigger.blade.php`
- Create: `lang/es/file_viewer.php`, `lang/en/file_viewer.php`
- Modify: `resources/views/layouts/app.blade.php`

### Task 3: Migrate views + cursor rule

**Files:**
- Modify: `app/Livewire/Documents/Panel.php`, `panel.blade.php`
- Modify: `inventory-panel` (remove local viewer), `InventoryPanel.php`
- Modify: payments, contracts, dashboard, settlement views
- Modify: `.cursor/rules/inmo-livewire.mdc`
- Test: `tests/Feature/FileViewer/FileViewerLayoutTest.php`
- Update: `tests/Feature/Units/UnitInventoryPanelTest.php` (sync event rename)
