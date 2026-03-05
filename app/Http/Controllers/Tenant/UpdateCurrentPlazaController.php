<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Plaza;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UpdateCurrentPlazaController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $organizationId = (int) ($user->organization_id ?? 0);
        abort_if($organizationId <= 0, 403);

        $validated = $request->validate([
            'plaza_id' => ['nullable', 'integer'],
        ]);

        $requestedPlazaId = $validated['plaza_id'] ?? null;
        if ($requestedPlazaId !== null && $requestedPlazaId !== '') {
            $exists = Plaza::query()
                ->withoutOrganizationScope()
                ->where('organization_id', $organizationId)
                ->whereKey((int) $requestedPlazaId)
                ->exists();

            if (! $exists) {
                throw ValidationException::withMessages([
                    'plaza_id' => 'La plaza seleccionada no pertenece a tu organización.',
                ]);
            }
        } else {
            $requestedPlazaId = null;
        }

        TenantContext::writeCurrentPlazaIdToSession($request->session(), $requestedPlazaId, (int) $user->id);

        return back();
    }
}
