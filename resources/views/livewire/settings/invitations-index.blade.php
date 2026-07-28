<section class="space-y-6">
    <x-ui.page-header
        :title="__('settings.invitations_title')"
        :description="__('settings.invitations_description')"
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
        <h2 class="text-sm font-semibold text-slate-900">{{ __('settings.org_governance') }}</h2>
        <p class="mt-1 text-xs text-slate-500">
            {{ __('settings.current_owner') }}
            <span class="font-medium text-slate-700">
                {{ $organization->ownerUser?->name ?? __('settings.no_owner') }}
                @if ($organization->ownerUser?->email)
                    ({{ $organization->ownerUser?->email }})
                @endif
            </span>
        </p>

        @if ($canTransferOwnership)
            <form wire:submit="transferOwnership" class="mt-3 flex flex-col gap-3 md:flex-row md:items-end">
                <div class="w-full md:max-w-sm">
                    <x-ui.select :label="__('settings.transfer_ownership_to')" wire:model="transferOwnerUserId">
                        <option value="">{{ __('settings.select_user') }}</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                        @endforeach
                    </x-ui.select>
                    @error('transferOwnerUserId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
                <x-ui.button type="submit" variant="secondary">
                    {{ __('settings.transfer_ownership') }}
                </x-ui.button>
            </form>
        @else
            <p class="mt-2 text-xs text-slate-500">
                {{ __('settings.only_owner_can_transfer') }}
            </p>
        @endif
    </x-ui.card>

    <x-ui.card :padding="true" class="!p-4">
        <h2 class="text-sm font-semibold text-slate-900">{{ __('settings.create_invitation') }}</h2>

        <form wire:submit="createInvitation" class="mt-4 grid gap-3 md:grid-cols-4">
            <div class="md:col-span-2">
                <x-ui.input
                    :label="__('common.email')"
                    type="email"
                    wire:model="email"
                    :placeholder="__('settings.email_placeholder')"
                />
                @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-ui.select :label="__('settings.role')" wire:model="role">
                    @foreach ($allowedRoles as $allowedRole)
                        <option value="{{ $allowedRole }}">{{ $allowedRole }}</option>
                    @endforeach
                </x-ui.select>
                @error('role') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <x-ui.input
                    :label="__('settings.expires_days')"
                    type="number"
                    min="1"
                    max="30"
                    wire:model="expiresInDays"
                />
                @error('expiresInDays') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-4">
                <x-ui.button type="submit">
                    {{ __('settings.create_invitation_button') }}
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.table>
        <x-slot:header>
            <h2 class="text-sm font-semibold text-slate-900">{{ __('settings.pending_invitations') }}</h2>
        </x-slot:header>
        <x-slot:head>
            <th class="px-4 py-2">{{ __('common.email') }}</th>
            <th class="px-4 py-2">{{ __('settings.role') }}</th>
            <th class="px-4 py-2">{{ __('settings.expires') }}</th>
            <th class="px-4 py-2 text-right">{{ __('common.action') }}</th>
        </x-slot:head>
        <x-slot:body>
            @forelse ($pendingInvitations as $invitation)
                <tr>
                    <td class="px-4 py-2">{{ $invitation->email }}</td>
                    <td class="px-4 py-2">{{ $invitation->role }}</td>
                    <td class="px-4 py-2"><x-ui.display-date :value="$invitation->expires_at" time /></td>
                    <td class="px-4 py-2 text-right">
                        <x-ui.button type="button" wire:click="revokeInvitation({{ $invitation->id }})" variant="secondary" size="sm">
                            {{ __('settings.revoke') }}
                        </x-ui.button>
                    </td>
                </tr>
            @empty
                <x-ui.empty-state :title="__('settings.empty_pending_invitations')" :colspan="4" />
            @endforelse
        </x-slot:body>
    </x-ui.table>

    <x-ui.table>
        <x-slot:header>
            <h2 class="text-sm font-semibold text-slate-900">{{ __('settings.organization_users') }}</h2>
        </x-slot:header>
        <x-slot:head>
            <th class="px-4 py-2">{{ __('common.name') }}</th>
            <th class="px-4 py-2">{{ __('common.email') }}</th>
            <th class="px-4 py-2">{{ __('settings.role') }}</th>
            <th class="px-4 py-2 text-right">{{ __('common.actions') }}</th>
        </x-slot:head>
        <x-slot:body>
            @foreach ($users as $user)
                <tr>
                    <td class="px-4 py-2">{{ $user->name }}</td>
                    <td class="px-4 py-2">{{ $user->email }}</td>
                    <td class="px-4 py-2">
                        @if ((int) $organization->owner_user_id === (int) $user->id)
                            <p class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-indigo-600">{{ __('settings.owner') }}</p>
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
                                {{ __('settings.save_role') }}
                            </x-ui.button>
                            <x-ui.button type="button" wire:click="removeUser({{ $user->id }})" variant="danger" size="sm">
                                {{ __('common.remove') }}
                            </x-ui.button>
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-slot:body>
    </x-ui.table>
</section>
