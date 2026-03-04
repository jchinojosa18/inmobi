# PDF Generation

## Goal
Provide a technical base to generate PDF receipts/finiquitos in Laravel without adding business financial logic yet.

## Package
- `barryvdh/laravel-dompdf` is used as the standard PDF renderer.
- Config file is versioned at `config/dompdf.php`.
- Current default paper is `letter`.

## Demo route
- Protected route: `/pdf/sample-receipt`
- Middleware: `role:Admin`
- Purpose: render a sample receipt PDF with dummy data:
  - folio
  - nombre
  - unidad
  - monto

## How it works
1. Route prepares dummy payload.
2. `Pdf::loadView('pdf.sample-receipt', $data)` renders Blade to PDF.
3. Response streams PDF to browser.

## Planned usage for real receipts/finiquitos
- Keep a dedicated PDF template per document type:
  - `resources/views/pdf/receipt.blade.php`
  - `resources/views/pdf/finiquito.blade.php`
- Provide data from application use-cases (actions/services), not from route closures.
- Persist generated files in configured document storage when required.
- Register the generated file in audit trail with actor, timestamp, and reason when applicable.

## Notes
- This setup is infrastructure-only.
- No payment settlement logic or accounting calculations are implemented here.
