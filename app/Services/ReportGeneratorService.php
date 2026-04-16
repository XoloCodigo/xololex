<?php

namespace App\Services;

use App\Models\Report;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

class ReportGeneratorService
{
    public function generate(Report $report): array
    {
        $report->load(['lawyer']);

        $wordPath = $this->generateWord($report);
        $pdfPath = $this->generatePdf($report);

        return [
            'word_path' => $wordPath,
            'pdf_path' => $pdfPath,
        ];
    }

    protected function generateWord(Report $report): string
    {
        $phpWord = new PhpWord();

        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection();

        // Header
        $section->addText('REPORTE DE VISITA', ['bold' => true, 'size' => 16], ['alignment' => 'center']);
        $section->addText("Folio: {$report->folio}", ['size' => 10, 'color' => '666666'], ['alignment' => 'center']);
        $section->addTextBreak(1);

        // Info general
        $this->addField($section, 'Fecha de visita', $report->visit_date->format('d/m/Y'));
        $this->addField($section, 'Abogado', $report->lawyer->name);
        $this->addField($section, 'Empresa visitada', $report->company_name);
        $this->addField($section, 'Contacto', "{$report->contact_met} — {$report->contact_position}");
        $section->addTextBreak(1);

        // Secciones
        $this->addSection($section, 'Motivo de la visita', $report->visit_reason);
        $this->addSection($section, 'Hallazgos principales', $report->findings);
        $this->addSection($section, 'Riesgos detectados', $report->risks);
        $this->addSection($section, 'Recomendaciones', $report->recommendations);

        if ($report->observations && strtolower($report->observations) !== 'ninguna') {
            $this->addSection($section, 'Observaciones adicionales', $report->observations);
        }

        // Footer
        $section->addTextBreak(2);
        $section->addText('_____________________________', [], ['alignment' => 'center']);
        $section->addText($report->lawyer->name, ['size' => 10], ['alignment' => 'center']);
        $section->addText('Firma', ['size' => 9, 'color' => '999999'], ['alignment' => 'center']);

        $filename = "reports/{$report->folio}.docx";
        $fullPath = storage_path("app/public/{$filename}");

        Storage::disk('public')->makeDirectory('reports');

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($fullPath);

        return $filename;
    }

    protected function generatePdf(Report $report): string
    {
        $pdf = Pdf::loadView('reports.visit', [
            'report' => $report,
        ]);

        $filename = "reports/{$report->folio}.pdf";
        Storage::disk('public')->put($filename, $pdf->output());

        return $filename;
    }

    protected function addField($section, string $label, string $value): void
    {
        $textRun = $section->addTextRun();
        $textRun->addText("{$label}: ", ['bold' => true, 'size' => 11]);
        $textRun->addText($value, ['size' => 11]);
    }

    protected function addSection($section, string $title, ?string $content): void
    {
        $section->addText($title, ['bold' => true, 'size' => 12, 'color' => '333333']);
        $section->addText($content ?? 'N/A', ['size' => 11]);
        $section->addTextBreak(1);
    }
}
