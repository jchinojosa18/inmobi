<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Finiquito contrato #{{ $contract->id }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 12px; }
        .muted { color: #64748b; }
        .box { border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px; text-align: left; }
        th { background: #f8fafc; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <h2 style="margin: 0 0 6px;">Finiquito contrato #{{ $contract->id }}</h2>
    <p class="muted" style="margin: 0 0 14px;">
        Inquilino: {{ $contract->tenant->full_name }} | Unidad: {{ $contract->unit->property->name }} / {{ $contract->unit->name }}
    </p>

    <div class="box">
        <strong>Resumen</strong><br>
        Fecha salida: {{ data_get($summary, 'move_out_date', optional($contract->ends_at)->format('Y-m-d')) }}<br>
        Depósito disponible: ${{ number_format((float) data_get($summary, 'deposit_available', 0), 2) }}<br>
        Depósito aplicado: ${{ number_format((float) data_get($summary, 'deposit_applied', abs((float) ($depositApply?->amount ?? 0))), 2) }}<br>
        Devolución de depósito: ${{ number_format((float) data_get($summary, 'deposit_refund', (float) ($refundExpense?->amount ?? 0)), 2) }}<br>
        Saldo por cobrar: ${{ number_format((float) data_get($summary, 'balance_to_collect', 0), 2) }}
    </div>

    <div class="box">
        <strong>Deducciones y cargos de salida</strong>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Concepto</th>
                    <th>Fecha</th>
                    <th class="text-right">Monto</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($moveoutCharges as $index => $charge)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ data_get($charge->meta, 'subtype', 'MOVEOUT') }}</td>
                        <td>{{ optional($charge->charge_date)->format('Y-m-d') }}</td>
                        <td class="text-right">${{ number_format((float) $charge->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">Sin conceptos registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <p style="margin-top: 8px;"><strong>Total cargos salida:</strong> ${{ number_format((float) $moveoutCharges->sum('amount'), 2) }}</p>
    </div>

    <div class="box">
        <strong>Evidencias</strong>
        <ul style="padding-left: 18px; margin: 8px 0 0;">
            @forelse ($moveoutCharges as $charge)
                @forelse ($charge->documents as $document)
                    <li>{{ $document->path }} ({{ $document->mime }})</li>
                @empty
                    @if ($loop->first)
                        <li>Sin evidencias adjuntas.</li>
                    @endif
                @endforelse
            @empty
                <li>Sin evidencias adjuntas.</li>
            @endforelse
        </ul>
    </div>
</body>
</html>
