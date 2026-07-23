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
}
