<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Document Upload Demo</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-slate-100 text-slate-900">
    <main class="mx-auto min-h-screen max-w-3xl p-6 md:p-10">
        <h1 class="mb-2 text-2xl font-semibold">Document Upload Demo</h1>
        <p class="mb-6 text-sm text-slate-600">
            Demo tecnico para validar flujo de evidencias/documentos sin vinculo a modulos de negocio.
        </p>

        <livewire:document-upload />
    </main>

    @livewireScripts
</body>
</html>
