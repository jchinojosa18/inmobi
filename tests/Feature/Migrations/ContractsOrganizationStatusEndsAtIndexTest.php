<?php

namespace Tests\Feature\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContractsOrganizationStatusEndsAtIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_contracts_have_organization_status_ends_at_index(): void
    {
        $this->assertTrue(
            Schema::hasIndex('contracts', 'contracts_organization_status_ends_at_index')
            || $this->sqliteHasIndex('contracts', 'contracts_organization_status_ends_at_index'),
            'Expected composite index contracts_organization_status_ends_at_index'
        );
    }

    private function sqliteHasIndex(string $table, string $indexName): bool
    {
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            return false;
        }

        $indexes = Schema::getConnection()->select("PRAGMA index_list('{$table}')");

        foreach ($indexes as $index) {
            if (($index->name ?? null) === $indexName) {
                return true;
            }
        }

        return false;
    }
}
