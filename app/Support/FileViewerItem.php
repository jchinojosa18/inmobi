<?php

namespace App\Support;

final class FileViewerItem
{
    /**
     * @return array{label: string, viewUrl: string, downloadUrl: string, mime: string, kind: string}
     */
    public static function fromDocumentRoute(int|string $documentId, string $label, ?string $mime = null): array
    {
        $mime = $mime ?? 'application/octet-stream';

        return [
            'label' => $label,
            'viewUrl' => route('documents.download', ['document' => $documentId, 'inline' => 1]),
            'downloadUrl' => route('documents.download', $documentId),
            'mime' => $mime,
            'kind' => self::previewKind($mime),
        ];
    }

    /**
     * @param  array<string, mixed>  $routeParameters
     * @return array{label: string, viewUrl: string, downloadUrl: string, mime: string, kind: string}
     */
    public static function fromPdfRoute(string $routeName, array $routeParameters, string $label): array
    {
        $downloadUrl = route($routeName, $routeParameters);

        return self::fromUrl($downloadUrl, $label);
    }

    /**
     * @return array{label: string, viewUrl: string, downloadUrl: string, mime: string, kind: string}
     */
    public static function fromUrl(string $url, string $label, string $mime = 'application/pdf'): array
    {
        return [
            'label' => $label,
            'viewUrl' => self::inlineUrl($url),
            'downloadUrl' => $url,
            'mime' => $mime,
            'kind' => self::previewKind($mime),
        ];
    }

    public static function previewKind(string $mime): string
    {
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }

        if ($mime === 'application/pdf') {
            return 'pdf';
        }

        return 'download';
    }

    private static function inlineUrl(string $url): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.'inline=1';
    }
}
