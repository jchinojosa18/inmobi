<?php

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

Route::view('/dashboard', 'dashboard')->middleware('auth')->name('dashboard');

Route::get('/admin/health', function () {
    return response()->json([
        'status' => 'ok',
        'scope' => 'admin',
    ]);
})->middleware('role:Admin');

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
