<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadController extends Controller
{
    public function __invoke(Request $request, int $document): StreamedResponse
    {
        $record = Document::query()
            ->withoutOrganizationScope()
            ->findOrFail($document);

        if ((int) $record->organization_id !== (int) $request->user()?->organization_id) {
            abort(403);
        }

        $disk = (string) data_get($record->meta, 'disk', config('filesystems.documents_disk', 'local'));

        if (! Storage::disk($disk)->exists($record->path)) {
            abort(404);
        }

        return Storage::disk($disk)->download($record->path, basename($record->path));
    }
}
