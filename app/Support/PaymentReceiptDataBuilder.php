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
        // Shared/WhatsApp links have no auth tenant context; scoped relations would
        // fail-closed (empty). Bind the payment's org for the duration of the build.
        $previousOrganizationId = TenantContext::currentOrganizationId();
        TenantContext::setOrganizationId((int) $payment->organization_id);

        try {
            $payment->loadMissing(['contract.tenant', 'contract.unit.property', 'allocations.charge']);

            $allocations = $payment->allocations
                ->map(function ($allocation): array {
                    return [
                        'charge_type' => (string) $allocation->charge?->type,
                        'period' => $allocation->charge?->period,
                        'charge_date' => DateDisplay::formatDate($allocation->charge?->charge_date, ''),
                        'amount' => (float) $allocation->amount,
                    ];
                })
                ->values()
                ->all();

            return [
                'folio' => $payment->receipt_folio,
                'paid_at' => DateDisplay::formatDateTime($payment->paid_at, ''),
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
        } finally {
            TenantContext::setOrganizationId($previousOrganizationId);
        }
    }
}
