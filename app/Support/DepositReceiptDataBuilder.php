<?php

namespace App\Support;

use App\Models\Charge;

class DepositReceiptDataBuilder
{
    /**
     * @return array{
     *     folio:string,
     *     received_at:string,
     *     method:string,
     *     amount:float,
     *     notes:?string,
     *     tenant_name:string,
     *     property_name:string,
     *     unit_name:string,
     *     contract_id:int
     * }
     */
    public function build(Charge $charge): array
    {
        $charge->loadMissing(['contract.tenant', 'contract.unit.property']);

        return [
            'folio' => (string) data_get($charge->meta, 'deposit_receipt_folio', ''),
            'received_at' => DateDisplay::formatDate(
                data_get($charge->meta, 'received_at') ?: $charge->charge_date,
                '',
            ),
            'method' => (string) data_get($charge->meta, 'method', ''),
            'amount' => (float) $charge->amount,
            'notes' => data_get($charge->meta, 'notes'),
            'tenant_name' => (string) $charge->contract?->tenant?->full_name,
            'property_name' => (string) $charge->contract?->unit?->property?->name,
            'unit_name' => (string) $charge->contract?->unit?->name,
            'contract_id' => (int) $charge->contract_id,
        ];
    }
}
