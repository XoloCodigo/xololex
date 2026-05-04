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

    protected const FIELDS_FOR_SUMMARY = [
        'company_name'      => 'Empresa',
        'visit_date'        => 'Fecha de visita',
        'visit_reason'      => 'Motivo',
        'contact_met'       => 'Contacto',
        'contact_position'  => 'Puesto',
        'findings'          => 'Hallazgos',
        'risks'             => 'Riesgos',
        'recommendations'   => 'Recomendaciones',
        'observations'      => 'Observaciones',
    ];

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
            'confirm_summary' => $this->confirmSummary($conversation, $phone, $message),
            'receive_edit_value' => $this->receiveEditValue($conversation, $phone, $message),
            'confirm_pdf' => $this->confirmPdf($conversation, $phone, $message),
            'receive_client_email' => $this->receiveClientEmail($conversation, $phone, $message),
            'confirm_email' => $this->confirmEmail($conversation, $phone, $message),
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

        // Si ya estaba en este step y faltan datos previos, la conversación está corrupta
        $existing = $conversation->data ?? [];
        $required = ['company_name', 'visit_date', 'visit_reason', 'contact_met', 'contact_position', 'findings'];
        foreach ($required as $field) {
            if (empty($existing[$field])) {
                $conversation->reset();
                $this->waha->sendText($phone, "⚠ La conversación quedó incompleta. Por favor, vuelve a iniciar escribiendo \"hola\".");
                return;
            }
        }

        $data = array_merge($existing, ['observations' => $message]);

        $this->waha->sendText($phone, "Formateando tu reporte con IA...");

        try {
            $data = $this->aiFormatter->formatReport($data);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('AI formatting failed, using raw text', [
                'error' => $e->getMessage(),
            ]);
        }

        $conversation->update([
            'step' => 'confirm_summary',
            'data' => $data,
        ]);

        $this->showSummary($conversation->fresh(), $phone);
    }

    protected function showSummary(Conversation $conversation, string $phone): void
    {
        $data = $conversation->data ?? [];
        $lines = ["📋 *Resumen del reporte*", ""];
        $i = 1;
        foreach (self::FIELDS_FOR_SUMMARY as $key => $label) {
            $value = $data[$key] ?? '';
            if ($key === 'visit_date' && $value) {
                try {
                    $value = \Carbon\Carbon::parse($value)->format('d/m/Y');
                } catch (\Throwable $e) {
                    // keep raw
                }
            }
            $lines[] = "*{$i}. {$label}:*\n{$value}";
            $i++;
        }
        $lines[] = "";
        $lines[] = "✅ Escribe *aprobar* para generar el PDF";
        $lines[] = "✏️ Escribe el *número* del campo a corregir";

        $this->waha->sendText($phone, implode("\n", $lines));
    }

    protected function confirmSummary(Conversation $conversation, string $phone, string $message): void
    {
        $msg = strtolower(trim($message));

        if (in_array($msg, ['aprobar', 'aprobado', 'ok', 'si', 'sí', 'aprueba', 'apruebo'], true)) {
            $this->generateAndPreviewPdf($conversation, $phone);
            return;
        }

        if (preg_match('/^\d+$/', $msg)) {
            $num = (int) $msg;
            $keys = array_keys(self::FIELDS_FOR_SUMMARY);
            if ($num < 1 || $num > count($keys)) {
                $this->waha->sendText($phone, "Número fuera de rango. Escribe un número del 1 al " . count($keys) . " o *aprobar*.");
                return;
            }
            $field = $keys[$num - 1];
            $label = self::FIELDS_FOR_SUMMARY[$field];

            $data = $conversation->data ?? [];
            $data['editing_field'] = $field;
            $conversation->update(['step' => 'receive_edit_value', 'data' => $data]);

            $hint = $field === 'visit_date' ? "\n\n_Formato: dd/mm/aaaa o \"hoy\"_" : '';
            $this->waha->sendText($phone, "Escribe el nuevo valor para *{$label}*:{$hint}");
            return;
        }

        $this->waha->sendText($phone, "No entendí la opción. Escribe *aprobar* o el *número* del campo a editar.");
    }

    protected function receiveEditValue(Conversation $conversation, string $phone, string $message): void
    {
        $value = trim($message);
        if ($value === '') {
            $this->waha->sendText($phone, "Escribe un valor para el campo seleccionado.");
            return;
        }

        $data = $conversation->data ?? [];
        $field = $data['editing_field'] ?? null;
        if (! $field) {
            $conversation->update(['step' => 'confirm_summary']);
            $this->showSummary($conversation->fresh(), $phone);
            return;
        }

        // Validación especial para fecha
        if ($field === 'visit_date') {
            $low = strtolower($value);
            if ($low === 'hoy') {
                $value = now()->format('Y-m-d');
            } else {
                try {
                    $value = \Carbon\Carbon::createFromFormat('d/m/Y', $value)->format('Y-m-d');
                } catch (\Throwable $e) {
                    $this->waha->sendText($phone, "Formato no válido. Usa dd/mm/aaaa o escribe \"hoy\".");
                    return;
                }
            }
        }

        $data[$field] = $value;
        unset($data['editing_field']);
        $conversation->update(['step' => 'confirm_summary', 'data' => $data]);

        $this->waha->sendText($phone, "✓ Campo actualizado.");
        $this->showSummary($conversation->fresh(), $phone);
    }

    protected function generateAndPreviewPdf(Conversation $conversation, string $phone): void
    {
        $data = $conversation->data ?? [];

        $this->waha->sendText($phone, "Generando PDF para revisión...");

        try {
            // Si ya hay un report_id (regeneración tras edición), reutilizar el folio
            $existingReportId = $data['report_id'] ?? null;
            if ($existingReportId && ($report = Report::find($existingReportId))) {
                $report->update([
                    'company_name' => $data['company_name'],
                    'visit_reason' => $data['visit_reason'],
                    'contact_met' => $data['contact_met'],
                    'contact_position' => $data['contact_position'],
                    'findings' => $data['findings'],
                    'risks' => $data['risks'],
                    'recommendations' => $data['recommendations'],
                    'observations' => $data['observations'],
                    'visit_date' => $data['visit_date'],
                ]);
            } else {
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
                    'status' => 'draft',
                ]);
            }

            $paths = $this->generator->generate($report);
            $report->update($paths);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Report generation failed', [
                'error' => $e->getMessage(),
                'lawyer_id' => $conversation->lawyer_id,
            ]);
            $conversation->reset();
            $this->waha->sendText($phone, "⚠ Hubo un error al generar el reporte. Vuelve a iniciar escribiendo \"hola\".");
            return;
        }

        $pdfUrl = asset('storage/' . $report->pdf_path);
        $this->waha->sendDocument($phone, $pdfUrl, "Reporte_{$report->folio}.pdf", "Vista previa del reporte {$report->folio}");

        $data['report_id'] = $report->id;
        $data['folio'] = $report->folio;
        $conversation->update([
            'step' => 'confirm_pdf',
            'data' => $data,
            'report_id' => $report->id,
        ]);

        $this->waha->sendText($phone, "¿El reporte está correcto?\n\n✅ Escribe *subir* para subirlo a SharePoint\n✏️ Escribe *editar* para corregir algún campo");
    }

    protected function confirmPdf(Conversation $conversation, string $phone, string $message): void
    {
        $msg = strtolower(trim($message));

        if (in_array($msg, ['subir', 'si', 'sí', 'ok', 'aprobar', 'subelo', 'súbelo'], true)) {
            $this->uploadAndAskEmail($conversation, $phone);
            return;
        }

        if (in_array($msg, ['editar', 'corregir', 'no', 'atras', 'atrás'], true)) {
            $conversation->update(['step' => 'confirm_summary']);
            $this->showSummary($conversation->fresh(), $phone);
            return;
        }

        $this->waha->sendText($phone, "No entendí. Escribe *subir* para subir el PDF o *editar* para corregirlo.");
    }

    protected function uploadAndAskEmail(Conversation $conversation, string $phone): void
    {
        $data = $conversation->data ?? [];
        $report = Report::find($data['report_id'] ?? null);
        if (! $report) {
            $conversation->reset();
            $this->waha->sendText($phone, "⚠ No se encontró el reporte. Vuelve a iniciar.");
            return;
        }

        $this->waha->sendText($phone, "Subiendo a SharePoint...");

        $sharepointUrl = $this->sharepoint->uploadFile($report->pdf_path, "{$report->folio}.pdf");
        $this->sharepoint->uploadFile($report->word_path, "{$report->folio}.docx");
        if ($sharepointUrl) {
            $report->update(['sharepoint_url' => $sharepointUrl, 'status' => 'completed']);
        } else {
            $report->update(['status' => 'completed']);
        }

        $spMsg = $sharepointUrl ? "📁 PDF subido a SharePoint" : "⚠ No se pudo subir a SharePoint";

        $lawyer = Lawyer::find($conversation->lawyer_id);
        if ($lawyer && ! empty($lawyer->email)) {
            $conversation->update(['step' => 'receive_client_email']);
            $this->waha->sendText($phone, "✓ Reporte {$report->folio} listo.\n{$spMsg}\n\n¿Quieres enviar este reporte por correo al cliente?\n\n_Escribe el correo del cliente o \"no\" para omitir_");
        } else {
            $conversation->reset();
            $this->waha->sendText($phone, "✓ Reporte {$report->folio} completado.\n{$spMsg}\n\n¿Necesitas algo más? Escribe \"hola\" para ver el menú.");
        }
    }

    protected function receiveClientEmail(Conversation $conversation, string $phone, string $message): void
    {
        $input = trim($message);

        if (strtolower($input) === 'no' || $input === '') {
            $folio = $conversation->data['folio'] ?? '';
            $conversation->reset();
            $this->waha->sendText($phone, "✓ Reporte {$folio} completado sin envío de correo.\n\n¿Necesitas algo más? Escribe \"hola\" para ver el menú.");
            return;
        }

        if (! filter_var($input, FILTER_VALIDATE_EMAIL)) {
            $this->waha->sendText($phone, "El correo no parece válido. Escribe un correo correcto o *no* para omitir.");
            return;
        }

        $data = $conversation->data ?? [];
        $data['client_email'] = $input;
        $conversation->update(['step' => 'confirm_email', 'data' => $data]);

        $this->waha->sendText($phone, "¿Es correcto este correo?\n\n📧 *{$input}*\n\n✅ Escribe *si* para enviar\n✏️ Escribe *no* para corregir");
    }

    protected function confirmEmail(Conversation $conversation, string $phone, string $message): void
    {
        $msg = strtolower(trim($message));

        if (in_array($msg, ['si', 'sí', 'ok', 'enviar', 'correcto', 'aprobar'], true)) {
            $clientEmail = $conversation->data['client_email'] ?? null;
            $folio = $conversation->data['folio'] ?? '';

            if (! $clientEmail) {
                $conversation->reset();
                $this->waha->sendText($phone, "⚠ No hay correo registrado. Vuelve a iniciar.");
                return;
            }

            $this->waha->sendText($phone, "Enviando correo...");
            $sent = $this->sendClientEmail($conversation, $clientEmail);
            $emailStatus = $sent
                ? "✉ Correo enviado a {$clientEmail}"
                : "⚠ No se pudo enviar el correo. Verifica con el administrador.";

            $conversation->reset();
            $this->waha->sendText($phone, "✓ Reporte {$folio} completado.\n{$emailStatus}\n\n¿Necesitas algo más? Escribe \"hola\" para ver el menú.");
            return;
        }

        if (in_array($msg, ['no', 'corregir', 'editar'], true)) {
            $data = $conversation->data ?? [];
            unset($data['client_email']);
            $conversation->update(['step' => 'receive_client_email', 'data' => $data]);
            $this->waha->sendText($phone, "Escribe nuevamente el correo del cliente o *no* para omitir el envío.");
            return;
        }

        $this->waha->sendText($phone, "No entendí. Escribe *si* para enviar o *no* para corregir el correo.");
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
