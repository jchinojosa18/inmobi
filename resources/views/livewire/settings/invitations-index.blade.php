<section class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">Usuarios e invitaciones</h1>
            <p class="mt-1 text-sm text-slate-600">Invita usuarios a tu empresa sin duplicar organización.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @error('remove_user')
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $message }}
        </div>
    @enderror

    <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-900">Crear invitación</h2>

        <form wire:submit="createInvitation" class="mt-4 grid gap-3 md:grid-cols-4">
            <div class="md:col-span-2">
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Email</label>
                <input
                    type="email"
                    wire:model="email"
                    placeholder="usuario@empresa.com"
                    class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm"
                >
                @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Rol</label>
                <select wire:model="role" class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm">
                    @foreach ($allowedRoles as $allowedRole)
                        <option value="{{ $allowedRole }}">{{ $allowedRole }}</option>
                    @endforeach
                </select>
                @error('role') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium uppercase tracking-wide text-slate-500">Expira (días)</label>
                <input
                    type="number"
                    min="1"
                    max="30"
                    wire:model="expiresInDays"
                    class="h-11 w-full rounded-lg border border-slate-300 px-3 text-sm"
                >
                @error('expiresInDays') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-4">
                <button type="submit" class="h-11 rounded-lg bg-slate-900 px-4 text-sm font-medium text-white hover:bg-slate-800">
                    Crear invitación
                </button>
            </div>
        </form>

        @if ($lastInvitationLink)
            <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Link de invitación</p>
                <div class="mt-2 flex flex-col gap-2 md:flex-row md:items-center">
                    <input type="text" readonly value="{{ $lastInvitationLink }}" class="h-10 w-full rounded border border-slate-300 bg-white px-3 text-xs text-slate-700">
                    <button
                        type="button"
                        onclick="navigator.clipboard.writeText(@js($lastInvitationLink))"
                        class="h-10 rounded border border-slate-300 bg-white px-3 text-xs font-medium text-slate-700 hover:bg-slate-100"
                    >
                        Copiar
                    </button>
                </div>
            </div>
        @endif
    </div>

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-4 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Invitaciones pendientes</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Email</th>
                        <th class="px-4 py-2">Rol</th>
                        <th class="px-4 py-2">Expira</th>
                        <th class="px-4 py-2 text-right">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($pendingInvitations as $invitation)
                        <tr>
                            <td class="px-4 py-2">{{ $invitation->email }}</td>
                            <td class="px-4 py-2">{{ $invitation->role }}</td>
                            <td class="px-4 py-2">{{ optional($invitation->expires_at)->timezone('America/Tijuana')->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-2 text-right">
                                <button
                                    type="button"
                                    wire:click="revokeInvitation({{ $invitation->id }})"
                                    class="rounded border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100"
                                >
                                    Revocar
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-slate-500">Sin invitaciones pendientes.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-4 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Usuarios de la organización</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2">Nombre</th>
                        <th class="px-4 py-2">Email</th>
                        <th class="px-4 py-2">Rol</th>
                        <th class="px-4 py-2 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($users as $user)
                        <tr>
                            <td class="px-4 py-2">{{ $user->name }}</td>
                            <td class="px-4 py-2">{{ $user->email }}</td>
                            <td class="px-4 py-2">
                                <select wire:model="userRoles.{{ $user->id }}" class="h-9 rounded border border-slate-300 px-2 text-sm">
                                    @foreach ($allowedRoles as $allowedRole)
                                        <option value="{{ $allowedRole }}">{{ $allowedRole }}</option>
                                    @endforeach
                                </select>
                                @error("userRoles.{$user->id}") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </td>
                            <td class="px-4 py-2 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <button
                                        type="button"
                                        wire:click="updateUserRole({{ $user->id }})"
                                        class="rounded border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100"
                                    >
                                        Guardar rol
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="removeUser({{ $user->id }})"
                                        class="rounded border border-rose-300 px-3 py-1.5 text-xs font-medium text-rose-700 hover:bg-rose-50"
                                    >
                                        Quitar
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
