<?php

namespace App\Support;

use App\Models\OrganizationSetting;
use Carbon\CarbonImmutable;

class OrganizationSettingsService
{
    public const DEFAULT_RECEIPT_FOLIO_MODE = OrganizationSetting::RECEIPT_MODE_ANNUAL;

    public const DEFAULT_RECEIPT_FOLIO_PREFIX = 'REC';

    public const DEFAULT_RECEIPT_FOLIO_PADDING = 6;

    public const DEFAULT_PENALTY_ROUNDING_SCALE = 2;

    public const DEFAULT_PENALTY_CALCULATION_POLICY = 'Interes compuesto diario sobre saldo vencido total (incluye RENT + PENALTY + cargos operativos - allocations aplicadas - saldo a favor) al corte D-1 23:59:59 America/Tijuana.';

    public const DEFAULT_WHATSAPP_TEMPLATE = 'Hola {tenant_name}, tu saldo pendiente en {unit_name} es ${amount_due}. Puedes revisar el recibo aqui: {shared_receipt_url}';

    public const DEFAULT_EMAIL_TEMPLATE = 'Hola {tenant_name},

Adjuntamos tu recibo de pago de la unidad {unit_name}.
Monto: ${amount_due}.
Tambien puedes consultarlo en: {shared_receipt_url}

Gracias.';

    public const DEFAULT_CONTRACT_WHATSAPP_TEMPLATE = 'Hola {tenant_name}, tu contrato de arrendamiento para {unit_name} esta listo. Renta: ${rent_amount}. Vigencia: {starts_at} - {ends_at}. Contrato: {shared_contract_url}';

    public const DEFAULT_CONTRACT_EMAIL_TEMPLATE = 'Hola {tenant_name},

Adjuntamos el contrato de arrendamiento de la unidad {unit_name}.
Renta mensual: ${rent_amount}.
Vigencia: {starts_at} a {ends_at}.
Tambien puedes consultarlo en: {shared_contract_url}

Saludos.';

    /**
     * @return array<string, int|string|null>
     */
    public function forOrganization(int $organizationId): array
    {
        $settings = OrganizationSetting::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->first();

        return $this->normalize($settings);
    }

    /**
     * @return array<string, int|string|null>
     */
    public function current(): array
    {
        $organizationId = TenantContext::currentOrganizationId() ?? auth()->user()?->organization_id;

        if (! is_int($organizationId) && ! is_numeric($organizationId)) {
            return $this->defaults();
        }

        return $this->forOrganization((int) $organizationId);
    }

    /**
     * @return array<string, int|string|null>
     */
    public function defaults(): array
    {
        return [
            'receipt_folio_mode' => self::DEFAULT_RECEIPT_FOLIO_MODE,
            'receipt_folio_prefix' => self::DEFAULT_RECEIPT_FOLIO_PREFIX,
            'receipt_folio_padding' => self::DEFAULT_RECEIPT_FOLIO_PADDING,
            'penalty_rounding_scale' => self::DEFAULT_PENALTY_ROUNDING_SCALE,
            'penalty_calculation_policy' => self::DEFAULT_PENALTY_CALCULATION_POLICY,
            'whatsapp_template' => self::DEFAULT_WHATSAPP_TEMPLATE,
            'email_template' => self::DEFAULT_EMAIL_TEMPLATE,
            'landlord_name' => null,
            'landlord_rep' => null,
            'contract_email_template' => self::DEFAULT_CONTRACT_EMAIL_TEMPLATE,
            'contract_whatsapp_template' => self::DEFAULT_CONTRACT_WHATSAPP_TEMPLATE,
            'onboarding_dismissed_until' => null,
        ];
    }

    public function dismissOnboardingForDays(int $organizationId, int $days = 7): CarbonImmutable
    {
        $dismissUntil = CarbonImmutable::now('America/Tijuana')
            ->addDays(max($days, 1))
            ->endOfDay();

        OrganizationSetting::query()
            ->withoutOrganizationScope()
            ->updateOrCreate(
                ['organization_id' => $organizationId],
                ['onboarding_dismissed_until' => $dismissUntil->toDateTimeString()]
            );

        return $dismissUntil;
    }

    public function isOnboardingDismissed(int $organizationId, ?CarbonImmutable $now = null): bool
    {
        $now ??= CarbonImmutable::now('America/Tijuana');

        $settings = OrganizationSetting::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->first();

        if ($settings?->onboarding_dismissed_until === null) {
            return false;
        }

        return CarbonImmutable::instance($settings->onboarding_dismissed_until)->greaterThan($now);
    }

    /**
     * @param  array<string, string|int|float|null>  $variables
     */
    public function renderTemplate(string $template, array $variables): string
    {
        $replacements = [];

        foreach ($this->templateVariables() as $variable) {
            $value = $variables[$variable] ?? '';
            $replacements['{'.$variable.'}'] = is_scalar($value) ? (string) $value : '';
        }

        return strtr($template, $replacements);
    }

    /**
     * @return list<string>
     */
    public function receiptTemplateVariables(): array
    {
        return [
            'tenant_name',
            'unit_name',
            'amount_due',
            'shared_receipt_url',
        ];
    }

    /**
     * @return list<string>
     */
    public function contractTemplateVariables(): array
    {
        return [
            'tenant_name',
            'unit_name',
            'shared_contract_url',
            'rent_amount',
            'starts_at',
            'ends_at',
        ];
    }

    /**
     * @return list<string>
     */
    public function templateVariables(): array
    {
        return array_values(array_unique(array_merge(
            $this->receiptTemplateVariables(),
            $this->contractTemplateVariables(),
        )));
    }

    /**
     * @return array<string, int|string|null>
     */
    private function normalize(?OrganizationSetting $settings): array
    {
        if ($settings === null) {
            return $this->defaults();
        }

        $defaults = $this->defaults();

        $mode = in_array($settings->receipt_folio_mode, OrganizationSetting::RECEIPT_MODES, true)
            ? $settings->receipt_folio_mode
            : $defaults['receipt_folio_mode'];

        $prefix = trim((string) $settings->receipt_folio_prefix);
        if ($prefix === '') {
            $prefix = (string) $defaults['receipt_folio_prefix'];
        }

        $padding = (int) $settings->receipt_folio_padding;
        if ($padding < 3 || $padding > 10) {
            $padding = (int) $defaults['receipt_folio_padding'];
        }

        $roundingScale = (int) $settings->penalty_rounding_scale;
        if ($roundingScale < 0 || $roundingScale > 6) {
            $roundingScale = (int) $defaults['penalty_rounding_scale'];
        }

        $penaltyPolicy = trim((string) $settings->penalty_calculation_policy);
        if ($penaltyPolicy === '') {
            $penaltyPolicy = (string) $defaults['penalty_calculation_policy'];
        }

        $whatsAppTemplate = trim((string) $settings->whatsapp_template);
        if ($whatsAppTemplate === '') {
            $whatsAppTemplate = (string) $defaults['whatsapp_template'];
        }

        $emailTemplate = trim((string) $settings->email_template);
        if ($emailTemplate === '') {
            $emailTemplate = (string) $defaults['email_template'];
        }

        $landlordName = $this->nullableTrimmed($settings->landlord_name);
        $landlordRep = $this->nullableTrimmed($settings->landlord_rep);

        $contractWhatsAppTemplate = trim((string) $settings->contract_whatsapp_template);
        if ($contractWhatsAppTemplate === '') {
            $contractWhatsAppTemplate = (string) $defaults['contract_whatsapp_template'];
        }

        $contractEmailTemplate = trim((string) $settings->contract_email_template);
        if ($contractEmailTemplate === '') {
            $contractEmailTemplate = (string) $defaults['contract_email_template'];
        }

        return [
            'receipt_folio_mode' => $mode,
            'receipt_folio_prefix' => $prefix,
            'receipt_folio_padding' => $padding,
            'penalty_rounding_scale' => $roundingScale,
            'penalty_calculation_policy' => $penaltyPolicy,
            'whatsapp_template' => $whatsAppTemplate,
            'email_template' => $emailTemplate,
            'landlord_name' => $landlordName,
            'landlord_rep' => $landlordRep,
            'contract_email_template' => $contractEmailTemplate,
            'contract_whatsapp_template' => $contractWhatsAppTemplate,
            'onboarding_dismissed_until' => $settings->onboarding_dismissed_until?->toDateTimeString(),
        ];
    }

    private function nullableTrimmed(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
