<?php

namespace Tests\Unit\Support;

use App\Support\FileViewerItem;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FileViewerItemTest extends TestCase
{
    public function test_from_url_appends_inline_query_parameter(): void
    {
        $item = FileViewerItem::fromUrl('https://example.test/receipt.pdf', 'Recibo');

        $this->assertSame('https://example.test/receipt.pdf?inline=1', $item['viewUrl']);
        $this->assertSame('https://example.test/receipt.pdf', $item['downloadUrl']);
        $this->assertSame('pdf', $item['kind']);
    }

    public function test_from_url_preserves_existing_query_string(): void
    {
        $item = FileViewerItem::fromUrl('https://example.test/receipt.pdf?foo=bar', 'Recibo');

        $this->assertSame('https://example.test/receipt.pdf?foo=bar&inline=1', $item['viewUrl']);
    }

    #[DataProvider('previewKindProvider')]
    public function test_preview_kind(string $mime, string $expected): void
    {
        $this->assertSame($expected, FileViewerItem::previewKind($mime));
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function previewKindProvider(): array
    {
        return [
            'image' => ['image/jpeg', 'image'],
            'pdf' => ['application/pdf', 'pdf'],
            'other' => ['application/octet-stream', 'download'],
        ];
    }
}
