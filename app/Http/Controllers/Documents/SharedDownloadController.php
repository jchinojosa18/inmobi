<?php

namespace App\Http\Controllers\Documents;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Document;
use App\Support\ContractDocumentCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SharedDownloadController extends Controller
{
    public function __invoke(Request $request, int $documentId): StreamedResponse
    {
        $document = Document::query()
            ->withoutOrganizationScope()
            ->findOrFail($documentId);

        if (
            $document->documentable_type !== Contract::class
            || $document->category !== ContractDocumentCategory::Contract
        ) {
            abort(404);
        }

        $disk = (string) data_get($document->meta, 'disk', config('filesystems.documents_disk', 'local'));

        if (! Storage::disk($disk)->exists($document->path)) {
            abort(404);
        }

        return Storage::disk($disk)->response($document->path, null, [
            'Content-Type' => $document->mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline',
        ]);
    }
}
