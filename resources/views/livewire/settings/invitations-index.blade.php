<section class="space-y-6">
    <x-ui.page-header
        title="Usuarios e invitaciones"
        description="Invita usuarios a tu empresa sin duplicar organización."
    />

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

    <x-ui.card :padding="true" class="!p-4">
        <h2 class="text-sm font-semibold text-slate-900">Gobernanza de organización</h2>
        <p class="mt-1 text-xs text-slate-500">
            Owner actual:
            <span class="font-medium text-slate-700">
                {{ $organization->ownerUser?->name ?? 'Sin owner' }}
                @if ($organization->ownerUser?->email)
                    ({{ $organization->ownerUser?->email }})
                @endif
            </span>
        </p>

        @if ($canTransferOwnership)
            <form wire:submit="transferOwnership" class="mt-3 flex flex-col gap-3 md:flex-row md:items-end">
                <div class="w-full md:max-w-sm">
                    <x-ui.select label="Transferir ownership a" wire:model="transferOwnerUserId">
                        <option value="">Selecciona usuario</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                        @endforeach
                    </x-ui.select>
                    @error('transferOwnerUserId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <x-ui.button type="submit" variant="secondary">
                    Transferir ownership
                </x-ui.button>
            </form>
        @else
            <p class="mt-2 text-xs text-slate-500">
                Solo el owner actual puede transferir ownership.
            </p>
        @endif
    </x-ui.card>

    <x-ui.card :padding="true" class="!p-4">
        <h2 class="text-sm font-semibold text-slate-900">Crear invitación</h2>

        <form wire:submit="createInvitation" class="mt-4 grid gap-3 md:grid-cols-4">
            <div class="md:col-span-2">
                <x-ui.input
                    label="Email"
                    type="email"
                    wire:model="email"
                    placeholder="usuario@empresa.com"
                />
                @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-ui.select label="Rol" wire:model="role">
                    @foreach ($allowedRoles as $allowedRole)
                        <option value="{{ $allowedRole }}">{{ $allowedRole }}</option>
                    @endforeach
                </x-ui.select>
                @error('role') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-ui.input
                    label="Expira (días)"
                    type="number"
                    min="1"
                    max="30"
                    wire:model="expiresInDays"
                />
                @error('expiresInDays') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-4">
                <x-ui.button type="submit">
                    Crear invitación
                </x-ui.button>
            </div>
        </form>

        @if ($lastInvitationLink)
            <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50 p-3">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Link de invitación</p>
                <div class="mt-2 flex flex-col gap-2 md:flex-row md:items-center">
                    <x-ui.input type="text" readonly value="{{ $lastInvitationLink }}" class="text-xs" />
                    <x-ui.button
                        type="button"
                        variant="secondary"
                        size="sm"
                        onclick="navigator.clipboard.writeText(@js($lastInvitationLink))"
                    >
                        Copiar
                    </x-ui.button>
                </div>
            </div>
        @endif
    </x-ui.card>

    <x-ui.table>
        <x-slot:header>
            <h2 class="text-sm font-semibold text-slate-900">Invitaciones pendientes</h2>
        </x-slot:header>
        <x-slot:head>
            <th class="px-4 py-2">Email</th>
            <th class="px-4 py-2">Rol</th>
            <th class="px-4 py-2">Expira</th>
            <th class="px-4 py-2 text-right">Acción</th>
        </x-slot:head>
        <x-slot:body>
            @forelse ($pendingInvitations as $invitation)
                <tr>
                    <td class="px-4 py-2">{{ $invitation->email }}</td>
                    <td class="px-4 py-2">{{ $invitation->role }}</td>
                    <td class="px-4 py-2">{{ optional($invitation->expires_at)->timezone('America/Tijuana')->format('Y-m-d H:i') }}</td>
                    <td class="px-4 py-2 text-right">
                        <x-ui.button type="button" wire:click="revokeInvitation({{ $invitation->id }})" variant="secondary" size="sm">
                            Revocar
                        </x-ui.button>
                    </td>
                </tr>
            @empty
                <x-ui.empty-state title="Sin invitaciones pendientes." :colspan="4" />
            @endforelse
        </x-slot:body>
    </x-ui.table>

    <x-ui.table>
        <x-slot:header>
            <h2 class="text-sm font-semibold text-slate-900">Usuarios de la organización</h2>
        </x-slot:header>
        <x-slot:head>
            <th class="px-4 py-2">Nombre</th>
            <th class="px-4 py-2">Email</th>
            <th class="px-4 py-2">Rol</th>
            <th class="px-4 py-2 text-right">Acciones</th>
        </x-slot:head>
        <x-slot:body>
            @foreach ($users as $user)
                <tr>
                    <td class="px-4 py-2">{{ $user->name }}</td>
                    <td class="px-4 py-2">{{ $user->email }}</td>
                    <td class="px-4 py-2">
                        @if ((int) $organization->owner_user_id === (int) $user->id)
                            <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-indigo-600">Owner</p>
                        @endif
                        <x-ui.select wire:model="userRoles.{{ $user->id }}">
                            @foreach ($allowedRoles as $allowedRole)
                                <option value="{{ $allowedRole }}">{{ $allowedRole }}</option>
                            @endforeach
                        </x-ui.select>
                        @error("userRoles.{$user->id}") <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </td>
                    <td class="px-4 py-2 text-right">
                        <div class="inline-flex items-center gap-2">
                            <x-ui.button type="button" wire:click="updateUserRole({{ $user->id }})" variant="secondary" size="sm">
                                Guardar rol
                            </x-ui.button>
                            <x-ui.button type="button" wire:click="removeUser({{ $user->id }})" variant="danger" size="sm">
                                Quitar
                            </x-ui.button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-slot:body>
    </x-ui.table>
</section>
