<?php

namespace App\Livewire\Reports;

use App\Support\CashFlowReportService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class CashFlow extends Component
{
    public string $date_from = '';

    public string $date_to = '';

    /**
     * @var array<string, array<string, string>>
     */
    protected $queryString = [
        'date_from' => ['except' => ''],
        'date_to' => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->date_from = now()->startOfMonth()->toDateString();
        $this->date_to = now()->toDateString();
    }

    public function render(): View
    {
        $this->validate($this->rules(), $this->messages());

        $dateFrom = CarbonImmutable::parse($this->date_from, 'America/Tijuana')->startOfDay();
        $dateTo = CarbonImmutable::parse($this->date_to, 'America/Tijuana')->endOfDay();
        $organizationId = (int) auth()->user()?->organization_id;
        $currentPlazaId = TenantContext::currentPlazaId();

        $report = app(CashFlowReportService::class)->build(
            $organizationId,
            $dateFrom,
            $dateTo,
            $currentPlazaId,
        );

        $exportUrl = route('reports.flow.export.csv', [
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
        ]);

        return view('livewire.reports.cash-flow', [
            ...$report,
            'exportUrl' => $exportUrl,
        ])->layout('layouts.app', ['title' => __('finance.cash_flow.title')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'date_from.required' => __('finance.validation.date_from_required'),
            'date_from.date' => __('finance.validation.date_from_invalid'),
            'date_to.required' => __('finance.validation.date_to_required'),
            'date_to.date' => __('finance.validation.date_to_invalid'),
            'date_to.after_or_equal' => __('finance.validation.date_to_after'),
        ];
    }
}
