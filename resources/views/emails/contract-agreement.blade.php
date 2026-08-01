<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $organizationName }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #0f172a;">
    <h2 style="margin-bottom: 4px;">Contrato de arrendamiento</h2>
    <p style="margin-top: 0; color: #475569;">Unidad: {{ trim((string) ($contract->unit?->property?->name.' / '.$contract->unit?->name)) ?: '—' }}</p>

    @if (isset($messageBody) && trim($messageBody) !== '')
        <p style="white-space: pre-line;">{{ $messageBody }}</p>
    @else
        <p>Hola {{ $contract->tenant?->full_name ?: 'cliente' }},</p>
        <p>Adjuntamos tu contrato de arrendamiento. También puedes verlo en este enlace temporal:</p>
        <p>
            <a href="{{ $shareUrl }}">{{ $shareUrl }}</a>
        </p>

        <p style="margin-top: 20px;">Saludos.</p>
    @endif
</body>
</html>
