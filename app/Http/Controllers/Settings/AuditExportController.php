<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('audit.export') ?? false, 403);

        $organizationId = (int) auth()->user()?->organization_id;

        $dateFrom = $request->string('date_from')->toString();
        $dateTo = $request->string('date_to')->toString();
        $actorUserId = $request->string('actor_user_id')->toString();
        $action = $request->string('action')->toString();
        $search = $request->string('search')->toString();

        $query = AuditEvent::query()
            ->where('organization_id', $organizationId)
            ->when($dateFrom !== '', fn (Builder $q) => $q->whereDate('occurred_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn (Builder $q) => $q->whereDate('occurred_at', '<=', $dateTo))
            ->when($actorUserId !== '', fn (Builder $q) => $q->where('actor_user_id', (int) $actorUserId))
            ->when($action !== '', fn (Builder $q) => $q->where('action', $action))
            ->when($search !== '', fn (Builder $q) => $q->where('summary', 'like', '%'.$search.'%'))
            ->with('actor:id,name,email')
            ->orderByDesc('occurred_at');

        $filename = 'auditoria-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['fecha', 'usuario', 'email_usuario', 'accion', 'resumen', 'entidad_tipo', 'entidad_id', 'ip']);

            $query->chunk(500, function ($events) use ($handle): void {
                foreach ($events as $event) {
                    fputcsv($handle, [
                        $event->occurred_at->timezone('America/Tijuana')->format('Y-m-d H:i:s'),
                        $event->actor?->name ?? 'Sistema',
                        $event->actor?->email ?? '',
                        $event->action,
                        $event->summary,
                        $event->auditable_type ? class_basename($event->auditable_type) : '',
                        $event->auditable_id ?? '',
                        $event->ip ?? '',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
