<?php

namespace App\Services\Bot;

use App\Models\Conversation;
use App\Models\Report;
use App\Services\AiFormatterService;
use App\Services\ReportGeneratorService;
use App\Services\SharePointService;
use App\Services\WahaService;

class ReportFlow
{
    public function __construct(
        protected WahaService $waha,
        protected ReportGeneratorService $generator,
        protected AiFormatterService $aiFormatter,
        protected SharePointService $sharepoint,
    ) {}

    public function handle(Conversation $conversation, string $phone, string $message): void
    {
        match ($conversation->step) {
            'ask_company' => $this->askCompany($conversation, $phone),
            'receive_company' => $this->receiveCompany($conversation, $phone, $message),
            'receive_visit_date' => $this->receiveVisitDate($conversation, $phone, $message),
            'receive_visit_reason' => $this->receiveVisitReason($conversation, $phone, $message),
            'receive_contact' => $this->receiveContact($conversation, $phone, $message),
            'receive_contact_position' => $this->receiveContactPosition($conversation, $phone, $message),
            'receive_findings' => $this->receiveFindings($conversation, $phone, $message),
            'receive_risks' => $this->receiveRisks($conversation, $phone, $message),
            'receive_recommendations' => $this->receiveRecommendations($conversation, $phone, $message),
            'receive_observations' => $this->receiveObservations($conversation, $phone, $message),
            default => $this->askCompany($conversation, $phone),
        };
    }

    protected function askCompany(Conversation $conversation, string $phone): void
    {
        $this->waha->sendText($phone, "¿A qué empresa visitaste?\n\n_Escribe el nombre de la empresa_");
        $conversation->setStep('report', 'receive_company');
    }

    protected function receiveCompany(Conversation $conversation, string $phone, string $message): void
    {
        $companyName = trim($message);

        if (strlen($companyName) < 2) {
            $this->waha->sendText($phone, "Escribe el nombre de la empresa.");
            return;
        }

        $conversation->setStep('report', 'receive_visit_date', [
            'company_name' => $companyName,
        ]);

        $this->waha->sendText($phone, "¿Cuándo fue la visita?\n\n_Escribe la fecha (ej: 15/04/2026) o \"hoy\"_");
    }

    protected function receiveVisitDate(Conversation $conversation, string $phone, string $message): void
    {
        $message = strtolower(trim($message));

        if ($message === 'hoy') {
            $date = now()->format('Y-m-d');
        } else {
            try {
                $date = \Carbon\Carbon::createFromFormat('d/m/Y', $message)->format('Y-m-d');
            } catch (\Exception $e) {
                $this->waha->sendText($phone, "Formato no válido. Usa dd/mm/aaaa o escribe \"hoy\".");
                return;
            }
        }

        $conversation->setStep('report', 'receive_visit_reason', ['visit_date' => $date]);
        $this->waha->sendText($phone, "¿Cuál fue el motivo de la visita?");
    }

    protected function receiveVisitReason(Conversation $conversation, string $phone, string $message): void
    {
        $conversation->setStep('report', 'receive_contact', ['visit_reason' => $message]);
        $this->waha->sendText($phone, "¿Con quién te reuniste? (nombre)");
    }

    protected function receiveContact(Conversation $conversation, string $phone, string $message): void
    {
        $conversation->setStep('report', 'receive_contact_position', ['contact_met' => $message]);
        $this->waha->sendText($phone, "¿Cuál es su puesto?");
    }

    protected function receiveContactPosition(Conversation $conversation, string $phone, string $message): void
    {
        $conversation->setStep('report', 'receive_findings', ['contact_position' => $message]);
        $this->waha->sendText($phone, "Describe los hallazgos principales de la visita:");
    }

    protected function receiveFindings(Conversation $conversation, string $phone, string $message): void
    {
        $conversation->setStep('report', 'receive_risks', ['findings' => $message]);
        $this->waha->sendText($phone, "¿Hay riesgos detectados? (escribe \"ninguno\" si no aplica)");
    }

    protected function receiveRisks(Conversation $conversation, string $phone, string $message): void
    {
        $conversation->setStep('report', 'receive_recommendations', ['risks' => $message]);
        $this->waha->sendText($phone, "¿Cuáles son tus recomendaciones?");
    }

    protected function receiveRecommendations(Conversation $conversation, string $phone, string $message): void
    {
        $conversation->setStep('report', 'receive_observations', ['recommendations' => $message]);
        $this->waha->sendText($phone, "¿Observaciones adicionales? (escribe \"ninguna\" si no hay)");
    }

    protected function receiveObservations(Conversation $conversation, string $phone, string $message): void
    {
        $data = array_merge($conversation->data ?? [], ['observations' => $message]);

        $this->waha->sendText($phone, "Formateando y generando tu reporte...");

        $data = $this->aiFormatter->formatReport($data);

        $report = Report::create([
            'folio' => Report::generateFolio(),
            'lawyer_id' => $conversation->lawyer_id,
            'company_name' => $data['company_name'],
            'visit_reason' => $data['visit_reason'],
            'contact_met' => $data['contact_met'],
            'contact_position' => $data['contact_position'],
            'findings' => $data['findings'],
            'risks' => $data['risks'],
            'recommendations' => $data['recommendations'],
            'observations' => $data['observations'],
            'visit_date' => $data['visit_date'],
            'status' => 'completed',
        ]);

        $paths = $this->generator->generate($report);
        $report->update($paths);

        $pdfUrl = asset('storage/' . $report->pdf_path);
        $this->waha->sendDocument($phone, $pdfUrl, "Reporte_{$report->folio}.pdf", "Reporte {$report->folio} generado.");

        // Subir PDF a SharePoint
        $sharepointUrl = $this->sharepoint->uploadFile($report->pdf_path, "{$report->folio}.pdf");
        if ($sharepointUrl) {
            $report->update(['sharepoint_url' => $sharepointUrl]);
        }

        $conversation->update(['report_id' => $report->id]);
        $conversation->reset();

        $spMsg = $sharepointUrl ? "\n📁 PDF subido a SharePoint" : "";
        $this->waha->sendText($phone, "✓ Reporte {$report->folio} completado y listo.{$spMsg}\n\n¿Necesitas algo más? Escribe \"hola\" para ver el menú.");
    }
}
