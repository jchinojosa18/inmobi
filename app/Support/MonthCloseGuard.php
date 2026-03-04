<?php

namespace App\Support;

use App\Models\Charge;
use App\Models\Document;
use App\Models\Expense;
use App\Models\MonthClose;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class MonthCloseGuard
{
    public static function assertPaymentMonthOpen(Payment $payment, string $operation): void
    {
        $organizationId = self::resolveOrganizationId($payment);
        $months = array_filter([
            self::monthFromDateValue($payment->getOriginal('paid_at')),
            self::monthFromDateValue($payment->paid_at),
        ]);

        self::assertMonthsOpen(
            organizationId: $organizationId,
            months: $months,
            message: "No puedes {$operation} pagos en meses cerrados.",
        );
    }

    public static function assertExpenseMonthOpen(Expense $expense, string $operation): void
    {
        $organizationId = self::resolveOrganizationId($expense);
        $months = array_filter([
            self::monthFromDateValue($expense->getOriginal('spent_at')),
            self::monthFromDateValue($expense->spent_at),
        ]);

        self::assertMonthsOpen(
            organizationId: $organizationId,
            months: $months,
            message: "No puedes {$operation} egresos en meses cerrados.",
        );
    }

    public static function assertChargeMonthOpen(Charge $charge, string $operation): void
    {
        $organizationId = self::resolveOrganizationId($charge);

        if ($charge->type === Charge::TYPE_ADJUSTMENT && ! self::isAdjustmentWithReason($charge)) {
            throw ValidationException::withMessages([
                'month_close' => 'Los ajustes requieren una razón obligatoria en meta.reason.',
            ]);
        }

        if ($operation === 'crear' && self::isAdjustmentWithReason($charge)) {
            $months = array_filter([
                self::monthFromChargeValues($charge->period, $charge->charge_date),
            ]);

            foreach (array_unique($months) as $month) {
                if (! is_string($month) || $month === '') {
                    continue;
                }

                if (! self::isMonthClosed($organizationId, $month)) {
                    continue;
                }

                // Closed months allow corrective ADJUSTMENT creation with explicit reason.
                return;
            }
        }

        $months = array_filter([
            self::monthFromChargeValues($charge->getOriginal('period'), $charge->getOriginal('charge_date')),
            self::monthFromChargeValues($charge->period, $charge->charge_date),
        ]);

        self::assertMonthsOpen(
            organizationId: $organizationId,
            months: $months,
            message: 'No puedes crear, editar ni eliminar cargos en meses cerrados. Usa un ajuste con razón obligatoria.',
        );
    }

    public static function assertDocumentMonthOpen(Document $document, string $operation): void
    {
        $organizationId = self::resolveOrganizationId($document);

        if ($organizationId <= 0) {
            return;
        }

        $documentable = self::resolveDocumentable($document);
        if ($documentable === null) {
            return;
        }

        $month = self::monthFromDocumentable($documentable);
        if ($month === null) {
            return;
        }

        self::assertMonthsOpen(
            organizationId: $organizationId,
            months: [$month],
            message: "No puedes {$operation} documentos asociados a periodos cerrados.",
        );
    }

    public static function isMonthClosed(int $organizationId, string $month): bool
    {
        if ($organizationId <= 0) {
            return false;
        }

        return MonthClose::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->where('month', $month)
            ->exists();
    }

    private static function assertMonthsOpen(int $organizationId, array $months, string $message): void
    {
        if ($organizationId <= 0) {
            return;
        }

        foreach (array_unique($months) as $month) {
            if (! is_string($month) || $month === '') {
                continue;
            }

            if (self::isMonthClosed($organizationId, $month)) {
                throw ValidationException::withMessages([
                    'month_close' => "{$message} Mes bloqueado: {$month}.",
                ]);
            }
        }
    }

    private static function monthFromDateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, 'America/Tijuana')->format('Y-m');
        } catch (\Throwable) {
            return null;
        }
    }

    private static function monthFromChargeValues(mixed $period, mixed $chargeDate): ?string
    {
        if (is_string($period) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            return $period;
        }

        return self::monthFromDateValue($chargeDate);
    }

    private static function isAdjustmentWithReason(Charge $charge): bool
    {
        if ($charge->type !== Charge::TYPE_ADJUSTMENT) {
            return false;
        }

        $meta = $charge->meta;
        if (! is_array($meta)) {
            return false;
        }

        $reason = trim((string) data_get($meta, 'reason', ''));

        return $reason !== '';
    }

    private static function resolveDocumentable(Document $document): ?Model
    {
        $documentableType = (string) $document->documentable_type;
        $documentableId = (int) $document->documentable_id;

        if ($documentableType === '' || $documentableId <= 0 || ! class_exists($documentableType)) {
            return null;
        }

        if (! is_subclass_of($documentableType, Model::class)) {
            return null;
        }

        /** @var class-string<Model> $documentableType */
        $query = $documentableType::query();
        if (method_exists($query->getModel(), 'scopeWithoutOrganizationScope')) {
            $query->withoutOrganizationScope();
        }

        return $query->find($documentableId);
    }

    private static function monthFromDocumentable(Model $documentable): ?string
    {
        if ($documentable instanceof Payment) {
            return self::monthFromDateValue($documentable->paid_at);
        }

        if ($documentable instanceof Expense) {
            return self::monthFromDateValue($documentable->spent_at);
        }

        if ($documentable instanceof Charge) {
            return self::monthFromChargeValues($documentable->period, $documentable->charge_date);
        }

        return null;
    }

    private static function resolveOrganizationId(Model $model): int
    {
        $organizationId = (int) ($model->getAttribute('organization_id') ?? 0);

        if ($organizationId > 0) {
            return $organizationId;
        }

        return (int) (auth()->user()?->organization_id ?? 0);
    }
}
