<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Recibo de pago</title>
</head>
<body style="font-family: Arial, sans-serif; color: #0f172a;">
    <h2 style="margin-bottom: 4px;">Recibo de pago {{ $receipt['folio'] }}</h2>
    <p style="margin-top: 0; color: #475569;">Fecha: {{ $receipt['paid_at'] }}</p>

    @if (isset($messageBody) && trim($messageBody) !== '')
        <p style="white-space: pre-line;">{{ $messageBody }}</p>
    @else
        <p>Hola {{ $receipt['tenant_name'] ?: 'cliente' }},</p>
        <p>Adjuntamos tu recibo de pago. También puedes verlo en este enlace temporal:</p>
        <p>
            <a href="{{ $shareUrl }}">{{ $shareUrl }}</a>
        </p>

        <p style="margin-top: 20px;">Gracias.</p>
    @endif
</body>
</html>
