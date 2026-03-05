<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\Plaza;
use Illuminate\Console\Command;

class BackfillDefaultPlazasCommand extends Command
{
    protected $signature = 'inmo:plazas:backfill-default {--organization-id= : Limita el backfill a una organization_id}';

    protected $description = 'Crea/normaliza la plaza default "Principal" para cada organización de forma idempotente';

    public function handle(): int
    {
        $organizationId = $this->option('organization-id');

        $query = Organization::query()->orderBy('id');
        if (is_numeric($organizationId)) {
            $query->where('id', (int) $organizationId);
        }

        $organizations = $query->get();
        if ($organizations->isEmpty()) {
            $this->info('No hay organizaciones para procesar.');

            return self::SUCCESS;
        }

        $created = 0;
        $normalized = 0;
        $unchanged = 0;

        foreach ($organizations as $organization) {
            $beforeTotal = Plaza::query()
                ->withoutOrganizationScope()
                ->where('organization_id', $organization->id)
                ->count();

            $beforeDefaultId = Plaza::query()
                ->withoutOrganizationScope()
                ->where('organization_id', $organization->id)
                ->where('is_default', true)
                ->value('id');

            $defaultPlaza = $organization->ensureDefaultPlaza();

            $afterTotal = Plaza::query()
                ->withoutOrganizationScope()
                ->where('organization_id', $organization->id)
                ->count();

            $status = 'unchanged';
            if ($afterTotal > $beforeTotal) {
                $status = 'created';
                $created++;
            } elseif ($beforeDefaultId === null) {
                $status = 'normalized';
                $normalized++;
            } else {
                $unchanged++;
            }

            $this->line(
                "Org #{$organization->id} {$organization->name}: {$status} "
                ."(default plaza #{$defaultPlaza->id} \"{$defaultPlaza->nombre}\")"
            );
        }

        $this->info("Backfill completo. created={$created} normalized={$normalized} unchanged={$unchanged}");

        return self::SUCCESS;
    }
}
