@extends('layouts.app', ['title' => 'Document Upload Demo'])

@section('content')
    <section class="mx-auto max-w-3xl">
        <h1 class="mb-2 text-2xl font-semibold">Document Upload Demo</h1>
        <p class="mb-6 text-sm text-slate-600">
            Demo técnico para validar flujo de evidencias/documentos sin vínculo a módulos de negocio.
        </p>

        <livewire:document-upload />
    </section>
@endsection
