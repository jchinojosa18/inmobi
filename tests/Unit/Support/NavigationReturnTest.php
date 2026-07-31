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

    public function test_resolve_payment_show_back_from_tenant_kardex_adds_contract_secondary(): void
    {
        $resolved = NavigationReturn::resolvePaymentShowBack(
            '/tenants/9?tab=charges',
            'Volver a Maria',
            '/contracts/5',
            'Volver al contrato',
        );

        $this->assertSame('/tenants/9?tab=charges', $resolved['primary']['url']);
        $this->assertSame('Volver a Maria', $resolved['primary']['label']);
        $this->assertSame('/contracts/5', $resolved['secondary']['url']);
        $this->assertSame('Volver al contrato', $resolved['secondary']['label']);
    }

    public function test_resolve_payment_show_back_from_payments_tab_preserves_active_tab(): void
    {
        $resolved = NavigationReturn::resolvePaymentShowBack(
            '/tenants/9?tab=payments',
            'Volver a Maria',
            '/contracts/5',
            'Volver al contrato',
        );

        $this->assertSame('/tenants/9?tab=payments', $resolved['primary']['url']);
        $this->assertSame('Volver a Maria', $resolved['primary']['label']);
        $this->assertSame('/contracts/5', $resolved['secondary']['url']);
        $this->assertSame('Volver al contrato', $resolved['secondary']['label']);
    }

    public function test_resolve_contract_show_back_from_tenant_kardex_adds_contracts_index_secondary(): void
    {
        $resolved = NavigationReturn::resolveContractShowBack(
            '/tenants/9',
            'Volver a Maria',
            '/contracts',
            'Volver a contratos',
        );

        $this->assertSame('/tenants/9', $resolved['primary']['url']);
        $this->assertSame('Volver a Maria', $resolved['primary']['label']);
        $this->assertSame('/contracts', $resolved['secondary']['url']);
        $this->assertSame('Volver a contratos', $resolved['secondary']['label']);
    }

    public function test_resolve_contract_show_back_preserves_active_kardex_tab(): void
    {
        $resolved = NavigationReturn::resolveContractShowBack(
            '/tenants/9?tab=charges',
            'Volver a Maria',
            '/contracts',
            'Volver a contratos',
        );

        $this->assertSame('/tenants/9?tab=charges', $resolved['primary']['url']);
    }

    public function test_resolve_payment_show_back_from_contract_uses_single_default(): void
    {
        $resolved = NavigationReturn::resolvePaymentShowBack(
            '/contracts/5',
            'Volver al contrato',
            '/contracts/5',
            'Volver al contrato',
        );

        $this->assertSame('/contracts/5', $resolved['primary']['url']);
        $this->assertNull($resolved['secondary']);
    }
}
