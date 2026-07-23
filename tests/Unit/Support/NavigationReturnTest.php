<?php

namespace Tests\Unit\Support;

use App\Support\NavigationReturn;
use Tests\TestCase;

class NavigationReturnTest extends TestCase
{
    public function test_sanitize_url_allows_relative_app_paths(): void
    {
        $this->assertSame('/tenants/1', NavigationReturn::sanitizeUrl('/tenants/1'));
        $this->assertSame('/tenants/1?tab=payments', NavigationReturn::sanitizeUrl('/tenants/1?tab=payments'));
    }

    public function test_sanitize_url_rejects_open_redirects(): void
    {
        $this->assertNull(NavigationReturn::sanitizeUrl('https://evil.test/phish'));
        $this->assertNull(NavigationReturn::sanitizeUrl('//evil.test/phish'));
        $this->assertNull(NavigationReturn::sanitizeUrl('javascript:alert(1)'));
        $this->assertNull(NavigationReturn::sanitizeUrl(''));
        $this->assertNull(NavigationReturn::sanitizeUrl(null));
    }

    public function test_append_adds_return_query_params(): void
    {
        $url = NavigationReturn::append(
            '/contracts/5',
            '/tenants/9',
            'Volver a Maria'
        );

        $this->assertStringContainsString('/contracts/5?', $url);
        $this->assertStringContainsString('return=%2Ftenants%2F9', $url);
        $this->assertStringContainsString('return_label=Volver%20a%20Maria', $url);
    }

    public function test_resolve_falls_back_to_defaults(): void
    {
        $resolved = NavigationReturn::resolve(
            'https://evil.test',
            null,
            '/contracts',
            'Volver a contratos'
        );

        $this->assertSame('/contracts', $resolved['url']);
        $this->assertSame('Volver a contratos', $resolved['label']);
    }
}
