<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Comprobante de depósito {{ $receipt['folio'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #0f172a; font-size: 12px; }
        .muted { color: #64748b; }
        .header { margin-bottom: 18px; }
        .box { border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; margin-bottom: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h2 style="margin: 0 0 6px;">Comprobante de depósito en garantía</h2>
        <div class="muted">Folio: {{ $receipt['folio'] }}</div>
        <div class="muted">Fecha de recepción: {{ $receipt['received_at'] }}</div>
    </div>

    <div class="box">
        <strong>Inquilino:</strong> {{ $receipt['tenant_name'] }}<br>
        <strong>Contrato:</strong> #{{ $receipt['contract_id'] }}<br>
        <strong>Propiedad / Unidad:</strong> {{ $receipt['property_name'] }} / {{ $receipt['unit_name'] }}<br>
        <strong>Método:</strong> {{ $receipt['method'] ?: 'N/A' }}<br>
        <strong>Notas:</strong> {{ $receipt['notes'] ?: 'N/A' }}<br>
        <strong>Monto de garantía recibido:</strong> ${{ number_format($receipt['amount'], 2) }}
    </div>

    <p class="muted">
        Este documento acredita la recepción de un depósito en garantía. No es un pago de renta ni ingreso operativo.
    </p>
</body>
</html>
