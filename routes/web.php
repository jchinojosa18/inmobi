<?php

use App\Http\Controllers\ContractSettlementPdfController;
use App\Http\Controllers\PaymentReceiptPdfController;
use App\Http\Controllers\Reports\CashFlowCsvExportController;
use App\Livewire\Admin\SystemStatus as AdminSystemStatus;
use App\Livewire\Cobranza\Index as CobranzaIndex;
use App\Livewire\Contracts\Form as ContractForm;
use App\Livewire\Contracts\Index as ContractsIndex;
use App\Livewire\Contracts\Show as ContractShow;
use App\Livewire\Dashboard\Index as DashboardIndex;
use App\Livewire\Expenses\Index as ExpensesIndex;
use App\Livewire\Houses\Create as HouseCreate;
use App\Livewire\Houses\Show as HouseShow;
use App\Livewire\MonthCloses\Index as MonthClosesIndex;
use App\Livewire\Payments\Create as PaymentCreate;
use App\Livewire\Payments\Show as PaymentShow;
use App\Livewire\Properties\Index as PropertiesIndex;
use App\Livewire\Reports\CashFlow as CashFlowReport;
use App\Livewire\Settings\Index as SettingsIndex;
use App\Livewire\Tenants\Index as TenantsIndex;
use App\Livewire\Units\Index as UnitsIndex;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function (): void {
    Route::view('/login', 'auth.login')->name('login');

    Route::post('/login', function (Request $request) {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'Las credenciales proporcionadas no son validas.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    })->name('login.store');
});

Route::post('/logout', function (Request $request) {
    Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('login');
})->middleware('auth')->name('logout');

Route::get('/dashboard', DashboardIndex::class)->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function (): void {
    Route::get('/properties', PropertiesIndex::class)->name('properties.index');
    Route::get('/properties/{property}/units', UnitsIndex::class)->name('properties.units.index');
    Route::get('/houses/create', HouseCreate::class)->name('houses.create');
    Route::get('/houses/{property}', HouseShow::class)->name('houses.show');
    Route::get('/tenants', TenantsIndex::class)->name('tenants.index');
    Route::get('/expenses', ExpensesIndex::class)->name('expenses.index');
    Route::get('/reports/flow', CashFlowReport::class)->name('reports.flow');
    Route::get('/reports/flow/export.csv', CashFlowCsvExportController::class)->name('reports.flow.export.csv');
    Route::get('/month-closes', MonthClosesIndex::class)->name('month-closes.index');
    Route::get('/settings', SettingsIndex::class)->name('settings.index');

    Route::get('/contracts', ContractsIndex::class)->name('contracts.index');
    Route::get('/contracts/create', ContractForm::class)->name('contracts.create');
    Route::get('/contracts/{contract}/edit', ContractForm::class)->name('contracts.edit');
    Route::get('/contracts/{contract}', ContractShow::class)->name('contracts.show');
    Route::get('/contracts/{contract}/settlements/{batch}/pdf', ContractSettlementPdfController::class)
        ->name('contracts.settlements.pdf');

    Route::get('/contracts/{contract}/payments/create', PaymentCreate::class)->name('contracts.payments.create');
    Route::get('/payments/{payment}', PaymentShow::class)->name('payments.show');
    Route::get('/payments/{paymentId}/receipt.pdf', PaymentReceiptPdfController::class)->name('payments.receipt.pdf');
    Route::get('/cobranza', CobranzaIndex::class)->name('cobranza.index');
});

Route::get('/receipts/{paymentId}/shared.pdf', PaymentReceiptPdfController::class)
    ->middleware('signed')
    ->name('payments.receipt.share');

Route::get('/admin/health', function () {
    return response()->json([
        'status' => 'ok',
        'scope' => 'admin',
    ]);
})->middleware('role:Admin');

Route::get('/admin/system', AdminSystemStatus::class)
    ->middleware(['auth', 'role:Admin'])
    ->name('admin.system');

Route::view('/demo/document-upload', 'document-upload-demo');

Route::get('/pdf/sample-receipt', function () {
    $receipt = [
        'folio' => 'REC-2026-0001',
        'nombre' => 'Juan Perez',
        'unidad' => 'Torre A - Depto 203',
        'monto' => 12500.00,
        'fecha' => now()->format('Y-m-d H:i'),
    ];

    return Pdf::loadView('pdf.sample-receipt', ['receipt' => $receipt])
        ->setPaper('letter', 'portrait')
        ->stream('sample-receipt.pdf');
})->middleware('role:Admin');
