<?php

namespace Tests\Feature\Locale;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogFinanceI18nTest extends TestCase
{
    use RefreshDatabase;

    public function test_properties_index_renders_english_when_locale_en(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $response = $this->actingAs($user)->get(route('properties.index'));

        $response->assertOk();
        $response->assertSee('Properties', false);
        $response->assertSee('Base catalog of properties per organization.', false);
        $response->assertSee('New property', false);
    }

    public function test_expenses_index_renders_english_when_locale_en(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $response = $this->actingAs($user)->get(route('expenses.index'));

        $response->assertOk();
        $response->assertSee('Expenses', false);
        $response->assertSee('Operational expense tracking and control.', false);
        $response->assertSee('Record expense', false);
    }
}
