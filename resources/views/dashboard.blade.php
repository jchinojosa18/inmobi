@extends('layouts.app', ['title' => 'Dashboard'])

@section('content')
    <section class="space-y-6">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
            <p class="mt-1 text-sm text-slate-600">
                Base operativa del sistema inmobiliario.
            </p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <a
                href="{{ route('properties.index') }}"
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-300"
            >
                <h2 class="text-sm font-semibold text-slate-900">Propiedades</h2>
                <p class="mt-2 text-sm text-slate-600">Gestión de catálogo de inmuebles.</p>
            </a>

            <a
                href="{{ route('tenants.index') }}"
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-300"
            >
                <h2 class="text-sm font-semibold text-slate-900">Inquilinos</h2>
                <p class="mt-2 text-sm text-slate-600">Alta y actualización de arrendatarios.</p>
            </a>

            <a
                href="{{ route('contracts.create') }}"
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-300"
            >
                <h2 class="text-sm font-semibold text-slate-900">Contratos</h2>
                <p class="mt-2 text-sm text-slate-600">Crear y editar contratos de arrendamiento.</p>
            </a>

            <a
                href="{{ route('expenses.index') }}"
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-300"
            >
                <h2 class="text-sm font-semibold text-slate-900">Egresos</h2>
                <p class="mt-2 text-sm text-slate-600">Alta y consulta de gastos operativos.</p>
            </a>

            <a
                href="{{ route('reports.flow') }}"
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-slate-300"
            >
                <h2 class="text-sm font-semibold text-slate-900">Flujo por rango</h2>
                <p class="mt-2 text-sm text-slate-600">Ingresos vs egresos y exportación CSV.</p>
            </a>
        </div>
    </section>
@endsection
