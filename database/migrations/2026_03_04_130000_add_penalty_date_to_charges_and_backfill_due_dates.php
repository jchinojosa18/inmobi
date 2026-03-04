<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('charges', function (Blueprint $table): void {
            $table->date('penalty_date')->nullable();
            $table->index(
                ['organization_id', 'contract_id', 'penalty_date'],
                'charges_contract_penalty_date_index'
            );
        });

        DB::table('charges')
            ->where('type', 'PENALTY')
            ->whereNull('penalty_date')
            ->update([
                'penalty_date' => DB::raw('charge_date'),
            ]);

        DB::table('charges')
            ->join('contracts', 'contracts.id', '=', 'charges.contract_id')
            ->where('charges.type', 'RENT')
            ->where(function ($query): void {
                $query
                    ->whereNull('charges.due_date')
                    ->orWhereNull('charges.grace_until');
            })
            ->select([
                'charges.id',
                'charges.period',
                'charges.due_date',
                'charges.grace_until',
                'contracts.due_day',
                'contracts.grace_days',
            ])
            ->orderBy('charges.id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    if (! is_string($row->period) || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $row->period)) {
                        continue;
                    }

                    $periodStart = CarbonImmutable::createFromFormat('Y-m', $row->period)->startOfMonth();
                    $dueDay = min(max((int) $row->due_day, 1), $periodStart->daysInMonth);

                    $dueDate = $row->due_date !== null
                        ? CarbonImmutable::parse($row->due_date)
                        : $periodStart->day($dueDay);

                    $graceUntil = $row->grace_until !== null
                        ? CarbonImmutable::parse($row->grace_until)
                        : $dueDate->addDays(max((int) $row->grace_days, 0));

                    DB::table('charges')
                        ->where('id', $row->id)
                        ->update([
                            'due_date' => $dueDate->toDateString(),
                            'grace_until' => $graceUntil->toDateString(),
                        ]);
                }
            }, 'charges.id');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table): void {
            $table->dropIndex('charges_contract_penalty_date_index');
            $table->dropColumn('penalty_date');
        });
    }
};
