<?php

namespace App\Actions\Contracts;

use App\Models\Charge;
use App\Models\Contract;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterDepositHoldAction
{
    public function execute(Contract $contract, float $amount, string $receivedAt, ?string $notes, ?int $userId): Charge
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'deposit_amount' => 'El depósito debe ser mayor a cero.',
            ]);
        }

        $receivedDate = CarbonImmutable::parse($receivedAt, 'America/Tijuana')->startOfDay();

        return DB::transaction(function () use ($contract, $amount, $receivedDate, $notes, $userId): Charge {
            $lockedContract = Contract::query()
                ->withoutOrganizationScope()
                ->lockForUpdate()
                ->findOrFail($contract->id);

            $existingCharge = Charge::query()
                ->withoutOrganizationScope()
                ->where('organization_id', $lockedContract->organization_id)
                ->where('contract_id', $lockedContract->id)
                ->where('type', Charge::TYPE_DEPOSIT_HOLD)
                ->whereDate('charge_date', $receivedDate->toDateString())
                ->where('amount', $amount)
                ->first();

            if ($existingCharge !== null) {
                return $existingCharge;
            }

            return Charge::query()
                ->withoutOrganizationScope()
                ->create([
                    'organization_id' => $lockedContract->organization_id,
                    'contract_id' => $lockedContract->id,
                    'unit_id' => $lockedContract->unit_id,
                    'type' => Charge::TYPE_DEPOSIT_HOLD,
                    'period' => $receivedDate->format('Y-m'),
                    'charge_date' => $receivedDate->toDateString(),
                    'amount' => round($amount, 2),
                    'meta' => [
                        'subtype' => 'RECEIVED',
                        'notes' => $notes,
                        'received_at' => $receivedDate->toDateString(),
                        'created_by_user_id' => $userId,
                    ],
                ]);
        }, 3);
    }
}
