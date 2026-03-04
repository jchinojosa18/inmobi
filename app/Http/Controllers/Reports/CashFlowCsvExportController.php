<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Support\OperatingIncomeService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashFlowCsvExportController extends Controller
{
    public function __invoke(Request $request, OperatingIncomeService $operatingIncomeService): StreamedResponse
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        $dateFrom = CarbonImmutable::parse($validated['date_from'])->startOfDay();
        $dateTo = CarbonImmutable::parse($validated['date_to'])->endOfDay();
        $organizationId = (int) $request->user()?->organization_id;

        $incomeDetails = $operatingIncomeService->allocationsForRange(
            organizationId: $organizationId,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
        );
        $incomeByType = $operatingIncomeService->totalsByTypeForRange(
            organizationId: $organizationId,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
        );

        $expenses = Expense::query()
            ->with(['unit.property'])
            ->whereBetween('spent_at', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->orderBy('spent_at')
            ->get();

        $incomeTotal = round((float) array_sum($incomeByType), 2);
        $expenseTotal = round((float) $expenses->sum('amount'), 2);
        $netTotal = round($incomeTotal - $expenseTotal, 2);
        $filename = 'cash-flow-'.$dateFrom->format('Ymd').'-'.$dateTo->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($incomeDetails, $incomeByType, $expenses, $incomeTotal, $expenseTotal, $netTotal): void {
            $output = fopen('php://output', 'w');

            if (! is_resource($output)) {
                return;
            }

            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($output, ['SECCION', 'INGRESOS_ALLOCATIONS']);
            fputcsv($output, ['fecha_pago', 'folio', 'contract_id', 'inquilino', 'propiedad', 'unidad', 'tipo', 'monto']);
            foreach ($incomeDetails as $row) {
                fputcsv($output, [
                    CarbonImmutable::parse($row['paid_at'])->timezone('America/Tijuana')->format('Y-m-d H:i'),
                    $row['receipt_folio'] ?? '',
                    $row['contract_id'],
                    $row['tenant_name'] ?? '',
                    $row['property_name'] ?? '',
                    $row['unit_name'] ?? ($row['unit_code'] ?? ''),
                    $row['charge_type'],
                    number_format((float) $row['allocated_amount'], 2, '.', ''),
                ]);
            }

            fputcsv($output, []);
            fputcsv($output, ['SECCION', 'INGRESOS_POR_TIPO']);
            fputcsv($output, ['tipo', 'total']);
            foreach ($incomeByType as $type => $total) {
                fputcsv($output, [(string) $type, number_format((float) $total, 2, '.', '')]);
            }

            fputcsv($output, []);
            fputcsv($output, ['SECCION', 'EGRESOS']);
            fputcsv($output, ['fecha', 'categoria', 'propiedad', 'unidad', 'proveedor', 'monto']);
            foreach ($expenses as $expense) {
                fputcsv($output, [
                    optional($expense->spent_at)->format('Y-m-d'),
                    $expense->category,
                    $expense->unit?->property?->name ?? '',
                    $expense->unit?->name ?? '',
                    $expense->vendor ?: 'Sin proveedor',
                    number_format((float) $expense->amount, 2, '.', ''),
                ]);
            }

            fputcsv($output, []);
            fputcsv($output, ['RESUMEN', '', '', '', 'TOTAL_INGRESOS', number_format($incomeTotal, 2, '.', '')]);
            fputcsv($output, ['RESUMEN', '', '', '', 'TOTAL_EGRESOS', number_format($expenseTotal, 2, '.', '')]);
            fputcsv($output, ['RESUMEN', '', '', '', 'NETO', number_format($netTotal, 2, '.', '')]);

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
