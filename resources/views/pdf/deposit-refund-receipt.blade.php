<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo devolución depósito #{{ $contract->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 12px; }
        .muted { color: #64748b; }
        .box { border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; margin-bottom: 12px; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <h2 style="margin: 0 0 6px;">Recibo de devolución de depósito</h2>
    <p class="muted" style="margin: 0 0 14px;">
        Folio: {{ data_get($summary, 'folio') }} |
        Inquilino: {{ $contract->tenant->full_name }} |
        Unidad: {{ $contract->unit->property->name }} / {{ $contract->unit->name }}
    </p>

    <div class="box">
        <strong>Contrato #{{ $contract->id }}</strong><br>
        Fecha: {{ \App\Support\DateDisplay::formatDate(data_get($summary, 'move_out_date', $contract->ends_at)) }}<br>
        Depósito disponible al finiquitar: ${{ number_format((float) data_get($summary, 'deposit_available', 0), 2) }}<br>
        Depósito aplicado: ${{ number_format((float) data_get($summary, 'deposit_applied', 0), 2) }}<br>
        Monto devolución: ${{ number_format((float) data_get($summary, 'deposit_refund', 0), 2) }}
        @php
            $creditRefunded = (float) data_get($summary, 'credit_refunded', 0);
            $depositRefund = (float) data_get($summary, 'deposit_refund', 0);
            $depositPortion = round($depositRefund - $creditRefunded, 2);
        @endphp
        @if ($creditRefunded > 0)
            <br><br>
            <strong>Desglose devolución</strong><br>
            Depósito: ${{ number_format($depositPortion, 2) }}<br>
            Saldo a favor: ${{ number_format($creditRefunded, 2) }}
        @endif
    </div>

    <p class="muted" style="margin: 0;">
        Comprobante de devolución emitido por finiquito. No constituye ingreso operativo.
    </p>
</body>
</html>
