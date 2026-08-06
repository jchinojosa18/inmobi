@if ($canManageContracts)
    <x-ui.confirm-modal
        :open="$showCancelConfirm"
        :title="$cancelBlockers === [] ? __('contracts.cancel_contract_title') : __('contracts.cancel_blocked_title')"
        :confirm-action="$cancelBlockers === [] ? 'executeCancelContract' : ''"
        cancel-action="cancelCancelConfirm"
        :confirm-label="__('contracts.cancel_confirm')"
        :cancel-label="__('common.cancel')"
        :aria-label="__('contracts.cancel_contract_title')"
        max-width="md"
    >
        @if ($cancelBlockers !== [])
            <ul class="list-disc space-y-2 pl-5 text-slate-700">
                @foreach ($cancelBlockers as $blocker)
                    <li class="break-words">
                        <span>{{ $blocker['message'] }}</span>
                        @if (! empty($blocker['action_url']) && ! empty($blocker['action_label']))
                            <a href="{{ $blocker['action_url'] }}" class="ml-1 font-medium text-indigo-600 hover:underline">
                                {{ $blocker['action_label'] }}
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
        @else
            <p class="mb-3 text-slate-700">{{ __('contracts.cancel_contract_help') }}</p>
            <label for="cancellation_reason" class="mb-1 block text-sm font-medium text-slate-700">
                {{ __('contracts.cancel_reason') }} *
            </label>
            <textarea
                id="cancellation_reason"
                wire:model="cancellation_reason"
                rows="3"
                placeholder="{{ __('contracts.cancel_reason_placeholder') }}"
                class="box-border max-w-full w-full rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-900 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
            ></textarea>
            @error('cancellation_reason')
                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
            @enderror
        @endif
    </x-ui.confirm-modal>
@endif
