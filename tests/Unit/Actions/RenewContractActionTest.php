<?php

namespace Tests\Unit\Actions;

use App\Actions\Contracts\RenewContractAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RenewContractActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::clear();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_renew_creates_new_contract_ends_old_and_transfers_deposit(): void
    {
        Storage::fake('local');
        config(['filesystems.documents_disk' => 'local']);

        [$source] = $this->createRenewableSource(depositHoldAmount: 9500.0);

        $result = app(RenewContractAction::class)->execute(
            source: $source,
            input: [
                'starts_at' => '2026-08-01',
                'ends_at' => '2027-07-31',
                'rent_amount' => 10000,
                'deposit_amount' => 10000,
                'register_difference' => true,
                'difference_received_at' => '2026-08-01',
                'difference_method' => Payment::METHOD_TRANSFER,
                'notes' => 'Diferencia de depósito',
            ],
            userId: null,
        );

        $newContract = $result->newContract->fresh();
        $oldContract = $result->oldContract->fresh();

        $this->assertNotSame($source->id, $newContract->id);
        $this->assertSame(Contract::STATUS_ENDED, $oldContract->status);
        $this->assertSame('2026-07-31', $oldContract->ends_at?->toDateString());

        $this->assertSame($source->id, data_get($newContract->meta, 'renewed_from_contract_id'));
        $this->assertSame($newContract->id, data_get($oldContract->meta, 'renewed_to_contract_id'));

        $this->assertNotNull($result->transferOutCharge);
        $this->assertSame(-9500.0, (float) $result->transferOutCharge->amount);
        $this->assertSame(Charge::TYPE_DEPOSIT_TRANSFER_OUT, $result->transferOutCharge->type);
        $this->assertSame($source->id, (int) $result->transferOutCharge->contract_id);

        $this->assertNotNull($result->transferredHoldCharge);
        $this->assertSame(9500.0, (float) $result->transferredHoldCharge->amount);
        $this->assertSame(Charge::TYPE_DEPOSIT_HOLD, $result->transferredHoldCharge->type);
        $this->assertSame($newContract->id, (int) $result->transferredHoldCharge->contract_id);
        $this->assertSame('deposit_transfer', data_get($result->transferredHoldCharge->meta, 'source'));
        $this->assertSame($source->id, data_get($result->transferredHoldCharge->meta, 'transferred_from_contract_id'));
        $this->assertSame($result->transferOutCharge->id, data_get($result->transferredHoldCharge->meta, 'transfer_out_charge_id'));
        $this->assertNull(data_get($result->transferredHoldCharge->meta, 'deposit_receipt_folio'));

        $this->assertNotNull($result->differenceHoldCharge);
        $this->assertSame(500.0, (float) $result->differenceHoldCharge->amount);
        $this->assertSame(Charge::TYPE_DEPOSIT_HOLD, $result->differenceHoldCharge->type);
        $this->assertNotNull(data_get($result->differenceHoldCharge->meta, 'deposit_receipt_folio'));

        $this->assertSame(9500.0, $result->transferredAmount);
        $this->assertSame(500.0, $result->differenceAmount);

        $holdsOnNew = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $newContract->id)
            ->where('type', Charge::TYPE_DEPOSIT_HOLD)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $holdsOnNew);
        $this->assertSame(10000.0, (float) $holdsOnNew->sum('amount'));

        $this->assertNotNull($result->document);
        $this->assertInstanceOf(Document::class, $result->document);
        $this->assertSame($newContract->id, (int) $result->document->documentable_id);
        $this->assertSame('lease_agreement', data_get($result->document->meta, 'kind'));
    }

    public function test_renew_blocked_when_outstanding_balance(): void
    {
        [$source] = $this->createRenewableSource(depositHoldAmount: 9500.0);

        Charge::factory()->create([
            'organization_id' => $source->organization_id,
            'contract_id' => $source->id,
            'unit_id' => $source->unit_id,
            'type' => Charge::TYPE_RENT,
            'period' => '2026-07',
            'charge_date' => '2026-07-01',
            'amount' => 500,
        ]);

        $this->expectException(ValidationException::class);

        app(RenewContractAction::class)->execute(
            source: $source,
            input: [
                'starts_at' => '2026-08-01',
                'ends_at' => '2027-07-31',
                'rent_amount' => 10000,
                'deposit_amount' => 10000,
                'register_difference' => false,
            ],
            userId: null,
        );
    }

    public function test_renew_blocked_when_landlord_name_missing(): void
    {
        [$source] = $this->createRenewableSource(depositHoldAmount: 9500.0, withLandlordName: false);

        try {
            app(RenewContractAction::class)->execute(
                source: $source,
                input: [
                    'starts_at' => '2026-08-01',
                    'ends_at' => '2027-07-31',
                    'rent_amount' => 10000,
                    'deposit_amount' => 10000,
                    'register_difference' => false,
                ],
                userId: null,
            );
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Configure el nombre del arrendador en Configuración antes de generar el contrato.',
                $exception->errors()['landlord_name'][0] ?? null,
            );
        }

        $source->refresh();

        $this->assertSame(Contract::STATUS_ACTIVE, $source->status);
        $this->assertNull(data_get($source->meta, 'renewed_to_contract_id'));
        $this->assertSame(
            1,
            Contract::query()
                ->withoutOrganizationScope()
                ->where('organization_id', $source->organization_id)
                ->where('unit_id', $source->unit_id)
                ->where('status', Contract::STATUS_ACTIVE)
                ->count(),
        );
    }

    public function test_renew_blocked_when_settlement_batch_present(): void
    {
        [$source] = $this->createRenewableSource(depositHoldAmount: 9500.0);

        $meta = is_array($source->meta) ? $source->meta : [];
        $meta['settlement_batch_id'] = 'batch-123';
        $source->update(['meta' => $meta]);

        $this->expectException(ValidationException::class);

        app(RenewContractAction::class)->execute(
            source: $source->fresh(),
            input: [
                'starts_at' => '2026-08-01',
                'ends_at' => '2027-07-31',
                'rent_amount' => 10000,
                'deposit_amount' => 10000,
                'register_difference' => false,
            ],
            userId: null,
        );
    }

    /**
     * @return array{0: Contract}
     */
    private function createRenewableSource(float $depositHoldAmount, bool $withLandlordName = true): array
    {
        $organization = Organization::factory()->create();
        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $tenant = Tenant::factory()->create(['organization_id' => $organization->id]);

        TenantContext::setOrganizationId($organization->id);

        if ($withLandlordName) {
            OrganizationSetting::query()
                ->withoutOrganizationScope()
                ->updateOrCreate(
                    ['organization_id' => $organization->id],
                    ['landlord_name' => 'Arrendador Demo S.A. de C.V.'],
                );
        }

        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            // Avoid auto-generated current-month RENT skewing outstanding balance guards.
            'rent_amount' => 0,
            'deposit_amount' => 9500,
            'due_day' => 5,
            'grace_days' => 3,
            'penalty_rate_daily' => 0.05,
            'status' => Contract::STATUS_ACTIVE,
            'starts_at' => '2025-08-01',
            'ends_at' => '2026-07-31',
            'meta' => null,
        ]);

        Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2025-08',
            'charge_date' => '2025-08-01',
            'amount' => $depositHoldAmount,
            'meta' => [
                'subtype' => 'RECEIVED',
                'received_at' => '2025-08-01',
            ],
        ]);

        return [$contract];
    }
}
