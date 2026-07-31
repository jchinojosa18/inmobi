<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $organizationName }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #0f172a;">
    <h2 style="margin-bottom: 4px;">Te invitaron a unirte a {{ $organizationName }}</h2>

    <p>Hola,</p>

    <p>
        @if ($invitedByName)
            {{ $invitedByName }} te invitó a formar parte de <strong>{{ $organizationName }}</strong>
        @else
            Fuiste invitado a formar parte de <strong>{{ $organizationName }}</strong>
        @endif
        con el rol <strong>{{ $role }}</strong>.
    </p>

    <p>
        <a href="{{ $acceptUrl }}" style="display: inline-block; padding: 10px 16px; background: #4f46e5; color: #ffffff; text-decoration: none; border-radius: 6px;">
            Aceptar invitación
        </a>
    </p>

    <p style="color: #475569; font-size: 14px;">
        O copia este enlace en tu navegador:<br>
        <a href="{{ $acceptUrl }}">{{ $acceptUrl }}</a>
    </p>

    @if ($expiresAt)
        <p style="color: #475569; font-size: 14px;">
            Este enlace expira el {{ $expiresAt }} (hora de Tijuana).
        </p>
    @endif
</body>
</html>
