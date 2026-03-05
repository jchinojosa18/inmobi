<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Contratos</h1>
            <p class="mt-1 text-sm text-slate-600">Listado operativo con foco en urgencia de cobranza.</p>
        </div>
        @if ($canManageContracts)
            <a
                href="{{ route('contracts.create') }}"
                class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800"
            >
                Nuevo contrato
            </a>
        @endif
    </div>

    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-6">
            <div class="md:col-span-2">
                <label for="contracts-search" class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">
                    Búsqueda global
                </label>
                <input
                    id="contracts-search"
                    type="text"
                    wire:model.live.debounce.300ms="q"
                    placeholder="Inquilino, contacto, propiedad, unidad..."
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                >
            </div>

            <div>
                <label for="contracts-status" class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">
                    Estado
                </label>
                <select
                    id="contracts-status"
                    wire:model.live="status_filter"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                >
                    <option value="active">Active</option>
                    <option value="ended">Ended</option>
                    <option value="all">All</option>
                </select>
            </div>

            <div>
                <label for="contracts-property" class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">
                    Propiedad
                </label>
                <select
                    id="contracts-property"
                    wire:model.live="property_id"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                >
                    <option value="">All</option>
                    @foreach ($properties as $property)
                        <option value="{{ $property->id }}">{{ $property->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="contracts-unit" class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">
                    Unidad
                </label>
                <select
                    id="contracts-unit"
                    wire:model.live="unit_id"
                    @disabled($property_id === '')
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm disabled:bg-slate-100"
                >
                    <option value="">All</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }}{{ $unit->code ? ' ('.$unit->code.')' : '' }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="contracts-overdue" class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">
                    Vencidos
                </label>
                <select
                    id="contracts-overdue"
                    wire:model.live="overdue_filter"
                    class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm"
                >
                    <option value="all">All</option>
                    <option value="overdue">Solo vencidos</option>
                    <option value="grace">Solo en gracia</option>
                    <option value="current">Solo al corriente</option>
                </select>
            </div>
        </div>

        <p class="mt-3 text-sm text-slate-600">
            Mostrando {{ $contracts->count() }} de {{ $contracts->total() }}
        </p>
    </div>

    @php
        $sortIndicator = static fn (string $field): string => $sort === $field ? ($dir === 'asc' ? '↑' : '↓') : '';
    @endphp

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Contrato</th>
                        <th class="px-4 py-3">
                            <button type="button" wire:click="sortBy('tenant')" class="inline-flex items-center gap-1 hover:text-slate-800">
                                Inquilino <span>{{ $sortIndicator('tenant') }}</span>
                            </button>
                        </th>
                        <th class="px-4 py-3">
                            <button type="button" wire:click="sortBy('unit')" class="inline-flex items-center gap-1 hover:text-slate-800">
                                Propiedad / Unidad <span>{{ $sortIndicator('unit') }}</span>
                            </button>
                        </th>
                        <th class="px-4 py-3">
                            <button type="button" wire:click="sortBy('next_due')" class="inline-flex items-center gap-1 hover:text-slate-800">
                                Próximo vencimiento <span>{{ $sortIndicator('next_due') }}</span>
                            </button>
                        </th>
                        <th class="px-4 py-3 text-right">Días atraso</th>
                        <th class="px-4 py-3 text-right">
                            <button type="button" wire:click="sortBy('balance')" class="ml-auto inline-flex items-center gap-1 hover:text-slate-800">
                                Saldo <span>{{ $sortIndicator('balance') }}</span>
                            </button>
                        </th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($contracts as $contract)
                        @php
                            $nextDueDate = $contract->next_due_date ? \Illuminate\Support\Carbon::parse($contract->next_due_date) : null;
                            $graceUntil = $contract->grace_until ? \Illuminate\Support\Carbon::parse($contract->grace_until) : null;
                            $overdueStatusLabel = match ($contract->overdue_status) {
                                'overdue' => 'Vencido',
                                'grace' => 'En gracia',
                                default => 'Al corriente',
                            };
                        @endphp
                        <tr>
                            <td class="px-4 py-3 align-top">
                                <p class="font-medium text-slate-900">#{{ $contract->id }}</p>
                                <span class="mt-1 inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $contract->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-700' }}">
                                    {{ $contract->status === 'active' ? 'Active' : 'Ended' }}
                                </span>
                            </td>

                            <td class="px-4 py-3 align-top">
                                <p class="font-medium text-slate-900">{{ $contract->tenant->full_name }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ $contract->tenant->email ?: 'Sin correo' }}
                                    @if ($contract->tenant->phone)
                                        · {{ $contract->tenant->phone }}
                                    @endif
                                </p>
                            </td>

                            <td class="px-4 py-3 align-top">
                                <p class="font-medium text-slate-900">{{ $contract->unit->property->name }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ $contract->unit->name }}
                                    @if ($contract->unit->code)
                                        ({{ $contract->unit->code }})
                                    @endif
                                </p>
                            </td>

                            <td class="px-4 py-3 align-top">
                                @if ($nextDueDate)
                                    <p class="font-medium text-slate-900">{{ $nextDueDate->format('Y-m-d') }}</p>
                                    <p class="text-xs text-slate-500">Gracia hasta: {{ $graceUntil?->format('Y-m-d') }}</p>
                                    <span class="mt-1 inline-flex rounded-full px-2 py-1 text-xs font-medium {{ $contract->overdue_status === 'overdue' ? 'bg-red-100 text-red-700' : ($contract->overdue_status === 'grace' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700') }}">
                                        {{ $overdueStatusLabel }}
                                    </span>
                                @else
                                    <p class="font-medium text-slate-700">Sin cargos</p>
                                    <p class="text-xs text-slate-500">Sin vencimientos de renta pendientes</p>
                                @endif
                            </td>

                            <td class="px-4 py-3 text-right align-top font-medium text-slate-900">
                                {{ max((int) $contract->overdue_days, 0) }}
                            </td>

                            <td class="px-4 py-3 text-right align-top">
                                <p class="font-medium text-slate-900">${{ number_format((float) $contract->pending_balance, 2) }}</p>
                                <p class="text-xs text-slate-500">Saldo a favor: ${{ number_format((float) $contract->credit_balance, 2) }}</p>
                            </td>

                            <td class="px-4 py-3 text-right align-top">
                                <div class="flex justify-end gap-2">
                                    <a
                                        href="{{ route('contracts.show', $contract) }}"
                                        class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                    >
                                        Ver
                                    </a>
                                    @if ($canCreatePayments)
                                        <a
                                            href="{{ route('contracts.payments.create', $contract) }}"
                                            class="rounded-md bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-500"
                                        >
                                            Registrar pago
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-slate-500">
                                No hay contratos con los filtros actuales.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-200 bg-slate-50 px-4 py-3">
            {{ $contracts->links() }}
        </div>
    </div>
</section>
