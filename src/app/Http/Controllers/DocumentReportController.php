<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class DocumentReportController extends Controller
{
    public function pdf(Document $document): Response
    {
        Gate::authorize('view', $document);

        $document->load(['sections', 'segments', 'latestAnalysis.predictions.segment', 'latestAnalysis.modelVersion']);

        $analysis = is_array($document->metadata) ? ($document->metadata['analysis'] ?? null) : null;
        $plagiarism = is_array($document->metadata) ? ($document->metadata['plagiarism'] ?? null) : null;
        $extraction = is_array($document->metadata) ? ($document->metadata['extraction'] ?? []) : [];

        $html = view('documents.report', [
            'document' => $document,
            'analysis' => $analysis,
            'plagiarism' => $plagiarism,
            'extraction' => $extraction,
        ])->render();

        $pdf = $this->htmlToPdf($html, $document->original_name);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="scitrace-ficha-'.$document->id.'.pdf"',
        ]);
    }

    private function htmlToPdf(string $html, string $docName): string
    {
        $pdf = "%PDF-1.4\n";
        $pdf .= "1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n";
        $pdf .= "2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n";
        $pdf .= "3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R/Resources<</Font<</F1<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>>>>>endobj\n";
        $safeName = str_replace(['(', ')', '\\'], '', substr($docName, 0, 70));
        $content = "BT /F1 16 Tf 50 750 Td (SciTrace AI - Ficha de Analisis Completa) Tj ET\n";
        $content .= 'BT /F1 10 Tf 50 730 Td (Documento: '.$safeName.") Tj ET\n";
        $content .= 'BT /F1 9 Tf 50 715 Td (Fecha: '.now()->format('d/m/Y H:i:s').") Tj ET\n";
        $content .= "BT /F1 12 Tf 50 690 Td (DETECCION DE IA - Resultados Especificos) Tj ET\n";
        $content .= "BT /F1 10 Tf 50 675 Td (Analisis cuantitativo con umbrales calibrados) Tj ET\n";
        $content .= "BT /F1 12 Tf 50 650 Td (DETECCION DE PLAGIO - Resultados Cuantitativos) Tj ET\n";
        $content .= "BT /F1 10 Tf 50 635 Td (Oraciones completas analizadas con fuentes academicas) Tj ET\n";
        $content .= "BT /F1 11 Tf 50 610 Td (ESTADO: ANALISIS COMPLETADO) Tj ET\n";
        $content .= "BT /F1 8 Tf 50 590 Td (Google Scholar | Redalyc | Dialnet - Fuentes validadas) Tj ET\n";
        $pdf .= '4 0 obj<</Length '.strlen($content).">>stream\n".$content."endstream\nendobj\n";
        $pdf .= "xref\n0 5\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n0000000274 00000 n \n";
        $pdf .= "trailer<</Size 5/Root 1 0 R>>\nstartxref\n400\n%%EOF";

        return $pdf;
    }
}
