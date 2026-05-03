<?php

namespace App\Services\Bot;

use App\Models\Conversation;
use App\Models\Lawyer;
use App\Models\Report;
use App\Services\AiFormatterService;
use App\Services\OutlookMailService;
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
        protected OutlookMailService $mail,
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
            'receive_client_email' => $this->receiveClientEmail($conversation, $phone, $message),
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
            $this->waha->sendText($phone, "¿A qué empresa visitaste?\n\n_Escribe el nombre de la empresa_");
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

        if ($message === '') {
            $this->waha->sendText($phone, "¿Cuándo fue la visita?\n\n_Escribe la fecha (ej: 15/04/2026) o \"hoy\"_");
            return;
        }

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
        $this->waha->sendText($phone, "¿Cuál fue el motivo de la visita?\n\n_🎙 Puedes responder con texto o audio_");
    }

    protected function receiveVisitReason(Conversation $conversation, string $phone, string $message): void
    {
        if (trim($message) === '') {
            $this->waha->sendText($phone, "¿Cuál fue el motivo de la visita?\n\n_🎙 Puedes responder con texto o audio_");
            return;
        }
        $conversation->setStep('report', 'receive_contact', ['visit_reason' => $message]);
        $this->waha->sendText($phone, "¿Con quién te reuniste? (nombre)");
    }

    protected function receiveContact(Conversation $conversation, string $phone, string $message): void
    {
        if (trim($message) === '') {
            $this->waha->sendText($phone, "¿Con quién te reuniste? (nombre)");
            return;
        }
        $conversation->setStep('report', 'receive_contact_position', ['contact_met' => $message]);
        $this->waha->sendText($phone, "¿Cuál es su puesto?");
    }

    protected function receiveContactPosition(Conversation $conversation, string $phone, string $message): void
    {
        if (trim($message) === '') {
            $this->waha->sendText($phone, "¿Cuál es su puesto?");
            return;
        }
        $conversation->setStep('report', 'receive_findings', ['contact_position' => $message]);
        $this->waha->sendText($phone, "Describe los hallazgos principales de la visita:\n\n_🎙 Puedes responder con texto o audio_");
    }

    protected function receiveFindings(Conversation $conversation, string $phone, string $message): void
    {
        if (trim($message) === '') {
            $this->waha->sendText($phone, "Describe los hallazgos principales de la visita:\n\n_🎙 Puedes responder con texto o audio_");
            return;
        }
        $conversation->setStep('report', 'receive_risks', ['findings' => $message]);
        $this->waha->sendText($phone, "¿Hay riesgos detectados? (escribe \"ninguno\" si no aplica)\n\n_🎙 Puedes responder con texto o audio_");
    }

    protected function receiveRisks(Conversation $conversation, string $phone, string $message): void
    {
        if (trim($message) === '') {
            $this->waha->sendText($phone, "¿Hay riesgos detectados? (escribe \"ninguno\" si no aplica)\n\n_🎙 Puedes responder con texto o audio_");
            return;
        }
        $conversation->setStep('report', 'receive_recommendations', ['risks' => $message]);
        $this->waha->sendText($phone, "¿Cuáles son tus recomendaciones?\n\n_🎙 Puedes responder con texto o audio_");
    }

    protected function receiveRecommendations(Conversation $conversation, string $phone, string $message): void
    {
        if (trim($message) === '') {
            $this->waha->sendText($phone, "¿Cuáles son tus recomendaciones?\n\n_🎙 Puedes responder con texto o audio_");
            return;
        }
        $conversation->setStep('report', 'receive_observations', ['recommendations' => $message]);
        $this->waha->sendText($phone, "¿Observaciones adicionales? (escribe \"ninguna\" si no hay)\n\n_🎙 Puedes responder con texto o audio_");
    }

    protected function receiveObservations(Conversation $conversation, string $phone, string $message): void
    {
        if (trim($message) === '') {
            $this->waha->sendText($phone, "¿Observaciones adicionales? (escribe \"ninguna\" si no hay)\n\n_🎙 Puedes responder con texto o audio_");
            return;
        }
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

        // Subir PDF y Word a SharePoint
        $sharepointUrl = $this->sharepoint->uploadFile($report->pdf_path, "{$report->folio}.pdf");
        $this->sharepoint->uploadFile($report->word_path, "{$report->folio}.docx");
        if ($sharepointUrl) {
            $report->update(['sharepoint_url' => $sharepointUrl]);
        }

        $conversation->update(['report_id' => $report->id]);
        $conversation->setStep('report', 'receive_client_email', [
            'report_id' => $report->id,
            'folio' => $report->folio,
            'company_name' => $report->company_name,
            'visit_date' => $report->visit_date->format('Y-m-d'),
        ]);

        $spMsg = $sharepointUrl ? "\n📁 PDF subido a SharePoint" : "";
        $this->waha->sendText($phone, "✓ Reporte {$report->folio} generado.{$spMsg}\n\n¿Quieres enviar este reporte por correo al cliente?\n\n_Escribe el correo del cliente o \"no\" para omitir_");
    }

    protected function receiveClientEmail(Conversation $conversation, string $phone, string $message): void
    {
        $input = trim($message);
        $clientEmail = null;

        if (strtolower($input) !== 'no' && $input !== '') {
            if (! filter_var($input, FILTER_VALIDATE_EMAIL)) {
                $this->waha->sendText($phone, "El correo no parece válido. Escribe un correo correcto o \"no\" para omitir.");
                return;
            }
            $clientEmail = $input;
        }

        $folio = $conversation->data['folio'] ?? '';
        $emailStatus = '';

        if ($clientEmail) {
            $sent = $this->sendClientEmail($conversation, $clientEmail);
            $emailStatus = $sent
                ? "\n✉ Correo enviado a {$clientEmail}"
                : "\n⚠ No se pudo enviar el correo. Verifica con el administrador.";
        }

        $conversation->reset();
        $this->waha->sendText($phone, "✓ Reporte {$folio} completado.{$emailStatus}\n\n¿Necesitas algo más? Escribe \"hola\" para ver el menú.");
    }

    protected function sendClientEmail(Conversation $conversation, string $clientEmail): bool
    {
        $lawyer = Lawyer::find($conversation->lawyer_id);
        if (! $lawyer || empty($lawyer->email)) {
            return false;
        }

        $reportId = $conversation->data['report_id'] ?? null;
        $report = $reportId ? Report::find($reportId) : null;
        if (! $report) {
            return false;
        }

        $attachments = [];
        if ($report->pdf_path) {
            $attachments[] = [
                'name' => "{$report->folio}.pdf",
                'storage_path' => $report->pdf_path,
                'contentType' => 'application/pdf',
            ];
        }

        $subject = "Reporte de visita {$report->folio} — {$report->company_name}";
        $bodyHtml = "
            <p>Estimado cliente,</p>
            <p>Le compartimos el reporte de visita correspondiente al " . $report->visit_date->format('d/m/Y') . ".</p>
            <ul>
                <li><strong>Folio:</strong> {$report->folio}</li>
                <li><strong>Empresa:</strong> {$report->company_name}</li>
                <li><strong>Motivo:</strong> {$report->visit_reason}</li>
                <li><strong>Contacto:</strong> {$report->contact_met} — {$report->contact_position}</li>
            </ul>
            <p>Adjunto encontrará el reporte completo en formato PDF.</p>
            <p>Saludos,<br>{$lawyer->name}</p>
        ";

        return $this->mail->send($lawyer->email, $clientEmail, $subject, $bodyHtml, $attachments);
    }
}
