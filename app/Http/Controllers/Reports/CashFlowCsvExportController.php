<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Support\CashFlowReportService;
use App\Support\DateDisplay;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashFlowCsvExportController extends Controller
{
    public function __invoke(Request $request, CashFlowReportService $cashFlowReportService): StreamedResponse
    {
        $validated = $request->validate([
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ]);

        $dateFrom = CarbonImmutable::parse($validated['date_from'], 'America/Tijuana')->startOfDay();
        $dateTo = CarbonImmutable::parse($validated['date_to'], 'America/Tijuana')->endOfDay();
        $organizationId = (int) $request->user()?->organization_id;
        $currentPlazaId = TenantContext::currentPlazaId();

        $report = $cashFlowReportService->build(
            $organizationId,
            $dateFrom,
            $dateTo,
            $currentPlazaId,
        );

        $filename = 'cash-flow-'.$dateFrom->format('Ymd').'-'.$dateTo->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($report): void {
            $output = fopen('php://output', 'w');

            if (! is_resource($output)) {
                return;
            }

            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($output, ['SECCION', 'INGRESOS_ALLOCATIONS']);
            fputcsv($output, ['fecha_pago', 'folio', 'contract_id', 'inquilino', 'propiedad', 'unidad', 'tipo', 'monto']);
            foreach ($report['incomeDetails'] as $row) {
                fputcsv($output, [
                    DateDisplay::formatDateTime($row['paid_at']),
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
            foreach ($report['incomeByType'] as $type => $total) {
                fputcsv($output, [(string) $type, number_format((float) $total, 2, '.', '')]);
            }

            fputcsv($output, []);
            fputcsv($output, ['SECCION', 'EGRESOS']);
            fputcsv($output, ['fecha', 'categoria', 'propiedad', 'unidad', 'proveedor', 'monto']);
            foreach ($report['expenses'] as $expense) {
                fputcsv($output, [
                    DateDisplay::formatDate($expense->spent_at),
                    $expense->expenseCategory?->name ?? '',
                    $expense->unit?->property?->name ?? '',
                    $expense->unit?->name ?? '',
                    $expense->vendor ?: 'Sin proveedor',
                    number_format((float) $expense->amount, 2, '.', ''),
                ]);
            }

            fputcsv($output, []);
            fputcsv($output, ['RESUMEN', '', '', '', 'TOTAL_INGRESOS', number_format($report['incomeTotal'], 2, '.', '')]);
            fputcsv($output, ['RESUMEN', '', '', '', 'TOTAL_EGRESOS', number_format($report['expenseTotal'], 2, '.', '')]);
            fputcsv($output, ['RESUMEN', '', '', '', 'NETO', number_format($report['netTotal'], 2, '.', '')]);
            fputcsv($output, ['RESUMEN', '', '', '', 'TOTAL_DEPOSITOS_RECIBIDOS', number_format($report['depositsReceivedTotal'], 2, '.', '')]);
            fputcsv($output, ['RESUMEN', '', '', '', 'INGRESO_BRUTO_CON_DEPOSITOS', number_format($report['grossCashInTotal'], 2, '.', '')]);

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
