<?php

namespace App\Support;

use Barryvdh\DomPDF\PDF;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

trait StreamsPdfResponse
{
    protected function streamPdf(PDF $pdf, Request $request, string $filename): Response
    {
        if ($request->boolean('inline')) {
            return $pdf->stream($filename);
        }

        return $pdf->download($filename);
    }
}
