<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo {{ $receipt['folio'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 12px; }
        .muted { color: #64748b; }
        .header { margin-bottom: 18px; }
        .box { border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px; text-align: left; }
        th { background: #f8fafc; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h2 style="margin: 0 0 6px;">Recibo de pago {{ $receipt['folio'] }}</h2>
        <div class="muted">Fecha de pago: {{ $receipt['paid_at'] }}</div>
    </div>

    <div class="box">
        <strong>Cliente:</strong> {{ $receipt['tenant_name'] }}<br>
        <strong>Propiedad / Unidad:</strong> {{ $receipt['property_name'] }} / {{ $receipt['unit_name'] }}<br>
        <strong>Método:</strong> {{ $receipt['method'] }}<br>
        <strong>Referencia:</strong> {{ $receipt['reference'] ?: 'N/A' }}<br>
        <strong>Monto recibido:</strong> ${{ number_format($receipt['amount'], 2) }}
    </div>

    <div class="box">
        <strong>Desglose de aplicación</strong>
        <table>
            <thead>
                <tr>
                    <th>Tipo de cargo</th>
                    <th>Periodo</th>
                    <th>Fecha cargo</th>
                    <th class="text-right">Aplicado</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($receipt['allocations'] as $allocation)
                    <tr>
                        <td>{{ $allocation['charge_type'] }}</td>
                        <td>{{ $allocation['period'] ?: 'N/A' }}</td>
                        <td>{{ $allocation['charge_date'] }}</td>
                        <td class="text-right">${{ number_format($allocation['amount'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">Sin allocations registradas.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <p style="margin-top: 10px;">
            <strong>Total aplicado:</strong> ${{ number_format($receipt['allocated_total'], 2) }}<br>
            <strong>Saldo a favor generado:</strong> ${{ number_format($receipt['credited_amount'], 2) }}
        </p>
    </div>
</body>
</html>
