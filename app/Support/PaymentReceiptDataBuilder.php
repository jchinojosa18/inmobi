<?php

namespace App\Support;

use App\Models\Payment;

class PaymentReceiptDataBuilder
{
    /**
     * @return array{
     *     folio:string,
     *     paid_at:string,
     *     method:string,
     *     amount:float,
     *     reference:?string,
     *     tenant_name:string,
     *     tenant_email:?string,
     *     tenant_phone:?string,
     *     property_name:string,
     *     unit_name:string,
     *     allocations:array<int, array{charge_type:string, period:?string, charge_date:string, amount:float}>,
     *     allocated_total:float,
     *     credited_amount:float
     * }
     */
    public function build(Payment $payment): array
    {
        $payment->loadMissing(['contract.tenant', 'contract.unit.property', 'allocations.charge']);

        $allocations = $payment->allocations
            ->map(function ($allocation): array {
                return [
                    'charge_type' => (string) $allocation->charge?->type,
                    'period' => $allocation->charge?->period,
                    'charge_date' => optional($allocation->charge?->charge_date)->format('Y-m-d') ?? '',
                    'amount' => (float) $allocation->amount,
                ];
            })
            ->values()
            ->all();

        return [
            'folio' => $payment->receipt_folio,
            'paid_at' => optional($payment->paid_at)->format('Y-m-d H:i') ?? '',
            'method' => $payment->method,
            'amount' => (float) $payment->amount,
            'reference' => $payment->reference,
            'tenant_name' => (string) $payment->contract?->tenant?->full_name,
            'tenant_email' => $payment->contract?->tenant?->email,
            'tenant_phone' => $payment->contract?->tenant?->phone,
            'property_name' => (string) $payment->contract?->unit?->property?->name,
            'unit_name' => (string) $payment->contract?->unit?->name,
            'allocations' => $allocations,
            'allocated_total' => (float) $payment->allocations->sum('amount'),
            'credited_amount' => (float) data_get($payment->meta, 'credited_amount', 0),
        ];
    }
}
