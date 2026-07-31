<?php

namespace App\Support;

final class NavigationReturn
{
    public const QUERY_URL = 'return';

    public const QUERY_LABEL = 'return_label';

    public static function sanitizeUrl(?string $url): ?string
    {
        if (! is_string($url)) {
            return null;
        }

        $url = trim($url);

        if ($url === '' || ! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return null;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) === 1) {
            return null;
        }

        if (strlen($url) > 2048) {
            return null;
        }

        return $url;
    }

    public static function sanitizeLabel(?string $label): ?string
    {
        if (! is_string($label)) {
            return null;
        }

        $label = trim(strip_tags($label));

        if ($label === '') {
            return null;
        }

        if (mb_strlen($label) > 120) {
            $label = mb_substr($label, 0, 117).'...';
        }

        return $label;
    }

    public static function append(string $url, string $returnUrl, string $returnLabel): string
    {
        $safeUrl = self::sanitizeUrl($returnUrl);
        $safeLabel = self::sanitizeLabel($returnLabel);

        if ($safeUrl === null || $safeLabel === null) {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query([
            self::QUERY_URL => $safeUrl,
            self::QUERY_LABEL => $safeLabel,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array{url: string, label: string}
     */
    public static function resolve(?string $returnUrl, ?string $returnLabel, string $defaultUrl, string $defaultLabel): array
    {
        $url = self::sanitizeUrl($returnUrl) ?? $defaultUrl;
        $label = self::sanitizeLabel($returnLabel) ?? $defaultLabel;

        return [
            'url' => $url,
            'label' => $label,
        ];
    }

    /**
     * @return array{tenant_id: int, tab: ?string}|null
     */
    public static function parseTenantKardexReturn(?string $url): ?array
    {
        $url = self::sanitizeUrl($url);

        if ($url === null || preg_match('#^/tenants/(\d+)(?:\?(.*))?$#', $url, $matches) !== 1) {
            return null;
        }

        $tab = null;

        if (isset($matches[2]) && $matches[2] !== '') {
            parse_str($matches[2], $query);
            $tab = is_string($query['tab'] ?? null) ? $query['tab'] : null;
        }

        return [
            'tenant_id' => (int) $matches[1],
            'tab' => $tab,
        ];
    }

    /**
     * @return array{
     *     primary: array{url: string, label: string},
     *     secondary: array{url: string, label: string}|null
     * }
     */
    public static function resolvePaymentShowBack(
        ?string $returnUrl,
        ?string $returnLabel,
        string $defaultContractUrl,
        string $defaultContractLabel,
        ?string $tenantName = null,
    ): array {
        return self::resolveKardexAwareShowBack(
            $returnUrl,
            $returnLabel,
            $defaultContractUrl,
            $defaultContractLabel,
            $tenantName,
        );
    }

    /**
     * @return array{
     *     primary: array{url: string, label: string},
     *     secondary: array{url: string, label: string}|null
     * }
     */
    public static function resolveContractShowBack(
        ?string $returnUrl,
        ?string $returnLabel,
        string $defaultContractsIndexUrl,
        string $defaultContractsIndexLabel,
        ?string $tenantName = null,
    ): array {
        return self::resolveKardexAwareShowBack(
            $returnUrl,
            $returnLabel,
            $defaultContractsIndexUrl,
            $defaultContractsIndexLabel,
            $tenantName,
        );
    }

    /**
     * @return array{
     *     primary: array{url: string, label: string},
     *     secondary: array{url: string, label: string}|null
     * }
     */
    private static function resolveKardexAwareShowBack(
        ?string $returnUrl,
        ?string $returnLabel,
        string $secondaryUrl,
        string $secondaryLabel,
        ?string $tenantName = null,
    ): array {
        $kardex = self::parseTenantKardexReturn($returnUrl);

        if ($kardex === null) {
            return [
                'primary' => self::resolve($returnUrl, $returnLabel, $secondaryUrl, $secondaryLabel),
                'secondary' => null,
            ];
        }

        $tenantUrl = self::sanitizeUrl($returnUrl)
            ?? route('tenants.show', $kardex['tenant_id'], false);
        $tenantLabel = self::sanitizeLabel($returnLabel)
            ?? ($tenantName !== null && $tenantName !== ''
                ? __('catalog.tenants.kardex.back_to_tenant', ['name' => $tenantName])
                : __('catalog.tenants.kardex.back_to_tenant_fallback'));

        return [
            'primary' => [
                'url' => $tenantUrl,
                'label' => $tenantLabel,
            ],
            'secondary' => [
                'url' => $secondaryUrl,
                'label' => $secondaryLabel,
            ],
        ];
    }
}
