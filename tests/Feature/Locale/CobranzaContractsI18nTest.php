<?php

namespace Tests\Feature\Locale;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CobranzaContractsI18nTest extends TestCase
{
    use RefreshDatabase;

    public function test_cobranza_renders_english_when_locale_en(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $response = $this->actingAs($user)->get(route('cobranza.index'));

        $response->assertOk();
        $response->assertSee('Collections', false);
        $response->assertSee('Daily panel for contract and collections tracking.', false);
        $response->assertSee('Overdue', false);
    }

    public function test_contracts_index_renders_english_filters_when_locale_en(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $response = $this->actingAs($user)->get(route('contracts.index'));

        $response->assertOk();
        $response->assertSee('Contracts', false);
        $response->assertSee('Global search', false);
        $response->assertSee('Status', false);
        $response->assertSee('Overdue only', false);
        $response->assertSee('In grace only', false);
    }
}
