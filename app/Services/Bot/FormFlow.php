<?php

namespace App\Services\Bot;

use App\Models\Conversation;
use App\Models\Lawyer;
use App\Models\Report;
use App\Services\OutlookMailService;
use App\Services\SharePointService;
use App\Services\WahaService;

class FormFlow
{
    public function __construct(
        protected WahaService $waha,
        protected SharePointService $sharepoint,
        protected OutlookMailService $mail,
    ) {}

    public function handle(Conversation $conversation, string $phone, string $message): void
    {
        match ($conversation->step) {
            'ask_report' => $this->askReport($conversation, $phone),
            'receive_report' => $this->receiveReport($conversation, $phone, $message),
            'receive_service_type' => $this->receiveServiceType($conversation, $phone, $message),
            'receive_activity_date' => $this->receiveActivityDate($conversation, $phone, $message),
            'receive_start_time' => $this->receiveStartTime($conversation, $phone, $message),
            'receive_end_time' => $this->receiveEndTime($conversation, $phone, $message),
            'receive_extra_hours' => $this->receiveExtraHours($conversation, $phone, $message),
            'receive_activity_type' => $this->receiveActivityType($conversation, $phone, $message),
            'receive_description' => $this->receiveDescription($conversation, $phone, $message),
            'receive_observations' => $this->receiveObservations($conversation, $phone, $message),
            'receive_client_email' => $this->receiveClientEmail($conversation, $phone, $message),
            default => $this->askReport($conversation, $phone),
        };
    }

    protected function askReport(Conversation $conversation, string $phone): void
    {
        $reports = Report::where('lawyer_id', $conversation->lawyer_id)
            ->latest()
            ->take(10)
            ->get();

        if ($reports->isEmpty()) {
            $this->waha->sendText($phone, "No tienes reportes. Primero crea un reporte de visita.");
            $conversation->reset();
            return;
        }

        $list = $reports->map(fn ($r, $i) => ($i + 1) . ". {$r->folio} — {$r->company_name} ({$r->visit_date->format('d/m/Y')})")->implode("\n");

        $this->waha->sendText($phone, "¿Para cuál reporte quieres llenar el formulario?\n\n{$list}\n\n_Escribe el número_");
        $conversation->setStep('form', 'receive_report', [
            'report_ids' => $reports->pluck('id')->toArray(),
        ]);
    }

    protected function receiveReport(Conversation $conversation, string $phone, string $message): void
    {
        $index = (int) $message - 1;
        $reportIds = $conversation->data['report_ids'] ?? [];

        if (! isset($reportIds[$index])) {
            $this->waha->sendText($phone, "Opción no válida. Escribe el número del reporte.");
            return;
        }

        $report = Report::find($reportIds[$index]);
        $conversation->setStep('form', 'receive_service_type', [
            'report_id' => $report->id,
            'folio' => $report->folio,
            'company_name' => $report->company_name,
        ]);
        $conversation->update(['report_id' => $report->id]);

        $this->waha->sendText($phone, "Tipo de servicio:\n\n1. Opción 1\n2. Opción 2\n3. Opción 3\n\n_Escribe el número_");
    }

    protected function receiveServiceType(Conversation $conversation, string $phone, string $message): void
    {
        $types = [1 => 'Opción 1', 2 => 'Opción 2', 3 => 'Opción 3'];
        $option = (int) $message;

        if (! isset($types[$option])) {
            $this->waha->sendText($phone, "Tipo de servicio:\n\n1. Opción 1\n2. Opción 2\n3. Opción 3\n\n_Escribe el número_");
            return;
        }

        $conversation->setStep('form', 'receive_activity_date', ['service_type' => $types[$option]]);
        $this->waha->sendText($phone, "¿Fecha de la actividad?\n\n_Escribe la fecha (ej: 16/04/2026) o \"hoy\"_");
    }

    protected function receiveActivityDate(Conversation $conversation, string $phone, string $message): void
    {
        $message = strtolower(trim($message));

        if ($message === '') {
            $this->waha->sendText($phone, "¿Fecha de la actividad?\n\n_Escribe la fecha (ej: 16/04/2026) o \"hoy\"_");
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

        $conversation->setStep('form', 'receive_start_time', ['activity_date' => $date]);
        $this->waha->sendText($phone, "¿Hora de inicio?\n\n_Escribe en formato HH:MM (ej: 09:00)_");
    }

    protected function normalizeTime(string $time): ?string
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m)) {
            return null;
        }
        $h = (int) $m[1];
        $min = (int) $m[2];
        if ($h > 23 || $min > 59) {
            return null;
        }
        return sprintf('%02d:%02d', $h, $min);
    }

    protected function receiveStartTime(Conversation $conversation, string $phone, string $message): void
    {
        $time = $this->normalizeTime(trim($message));

        if (! $time) {
            $this->waha->sendText($phone, "Formato no válido. Escribe la hora como HH:MM (ej: 09:00).");
            return;
        }

        $conversation->setStep('form', 'receive_end_time', ['start_time' => $time]);
        $this->waha->sendText($phone, "¿Hora de fin?\n\n_Escribe en formato HH:MM (ej: 13:00)_");
    }

    protected function receiveEndTime(Conversation $conversation, string $phone, string $message): void
    {
        $time = $this->normalizeTime(trim($message));

        if (! $time) {
            $this->waha->sendText($phone, "Formato no válido. Escribe la hora como HH:MM (ej: 13:00).");
            return;
        }

        $startTime = $conversation->data['start_time'] ?? '00:00';
        $start = \Carbon\Carbon::createFromFormat('H:i', $startTime);
        $end = \Carbon\Carbon::createFromFormat('H:i', $time);
        $duration = $start->diffInMinutes($end) / 60;

        $conversation->setStep('form', 'receive_extra_hours', [
            'end_time' => $time,
            'duration' => round($duration, 2),
        ]);
        $this->waha->sendText($phone, "¿Horas fuera de jornada laboral?\n\n_Escribe el número (ej: 1.5) o \"0\" si no aplica_");
    }

    protected function receiveExtraHours(Conversation $conversation, string $phone, string $message): void
    {
        if (trim($message) === '') {
            $this->waha->sendText($phone, "¿Horas fuera de jornada laboral?\n\n_Escribe el número (ej: 1.5) o \"0\" si no aplica_");
            return;
        }
        $conversation->setStep('form', 'receive_activity_type', ['extra_hours' => trim($message)]);
        $this->waha->sendText($phone, "Tipo de actividad:\n\n1. Opción 1\n2. Opción 2\n3. Opción 3\n\n_Escribe el número_");
    }

    protected function receiveActivityType(Conversation $conversation, string $phone, string $message): void
    {
        $types = [1 => 'Opción 1', 2 => 'Opción 2', 3 => 'Opción 3'];
        $option = (int) $message;

        if (! isset($types[$option])) {
            $this->waha->sendText($phone, "Opción no válida. Escribe 1, 2 o 3.");
            return;
        }

        $conversation->setStep('form', 'receive_description', ['activity_type' => $types[$option]]);
        $this->waha->sendText($phone, "Describe brevemente la actividad realizada:\n\n_🎙 Puedes responder con texto o audio_");
    }

    protected function receiveDescription(Conversation $conversation, string $phone, string $message): void
    {
        if (trim($message) === '') {
            $this->waha->sendText($phone, "Describe brevemente la actividad realizada:\n\n_🎙 Puedes responder con texto o audio_");
            return;
        }
        $conversation->setStep('form', 'receive_observations', ['description' => $message]);
        $this->waha->sendText($phone, "¿Observaciones? (escribe \"ninguna\" si no hay)\n\n_🎙 Puedes responder con texto o audio_");
    }

    protected function receiveObservations(Conversation $conversation, string $phone, string $message): void
    {
        $observations = strtolower(trim($message)) === 'ninguna' ? '' : $message;
        $conversation->setStep('form', 'receive_client_email', ['observations' => $observations]);
        $this->waha->sendText($phone, "¿Quieres enviar copia por correo al cliente?\n\n_Escribe el correo del cliente o \"no\" para omitir_");
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

        $data = array_merge($conversation->data ?? [], ['client_email' => $clientEmail]);

        $this->waha->sendText($phone, "Registrando en SharePoint...");

        $activityDate = $data['activity_date'];
        $fields = [
            'Title' => $data['folio'],
            'Cliente_x002f_Empresa' => $data['company_name'],
            'TipodeServicio' => $data['service_type'],
            'Fechadeactividad' => $activityDate,
            'Horadeinicio' => "{$activityDate}T{$data['start_time']}:00Z",
            'HoraFin' => "{$activityDate}T{$data['end_time']}:00Z",
            'Duraci_x00f3_n' => $data['duration'],
            'Horasfueradejordanalaboral' => $data['extra_hours'],
            'Tipodeactividad' => $data['activity_type'],
            'Descripci_x00f3_n' => $data['description'],
            'Observaciones' => $data['observations'],
        ];

        $result = $this->sharepoint->insertListItem($fields);

        // Enviar correo si aplica
        $emailStatus = '';
        if ($clientEmail && $result) {
            $emailStatus = $this->sendClientEmail($conversation, $data, $clientEmail)
                ? "\n✉ Correo enviado a {$clientEmail}"
                : "\n⚠ No se pudo enviar el correo al cliente";
        }

        $conversation->reset();

        if ($result) {
            $this->waha->sendText($phone, "✓ Formulario registrado en SharePoint para reporte {$data['folio']}\n\n*Resumen:*\n• Empresa: {$data['company_name']}\n• Servicio: {$data['service_type']}\n• Fecha: {$activityDate}\n• Horario: {$data['start_time']} - {$data['end_time']}\n• Duración: {$data['duration']} hrs{$emailStatus}\n\n¿Necesitas algo más? Escribe \"hola\" para ver el menú.");
        } else {
            $this->waha->sendText($phone, "⚠ Hubo un error al registrar en SharePoint. Los datos se guardaron localmente. Contacta al administrador.\n\n¿Necesitas algo más? Escribe \"hola\" para ver el menú.");
        }
    }

    protected function sendClientEmail(Conversation $conversation, array $data, string $clientEmail): bool
    {
        $lawyer = Lawyer::find($conversation->lawyer_id);
        if (! $lawyer || empty($lawyer->email)) {
            return false;
        }

        $report = Report::find($data['report_id']);
        $attachments = [];
        if ($report && $report->pdf_path) {
            $attachments[] = [
                'name' => "{$report->folio}.pdf",
                'storage_path' => $report->pdf_path,
                'contentType' => 'application/pdf',
            ];
        }

        $subject = "Reporte de visita {$data['folio']} — {$data['company_name']}";
        $bodyHtml = "
            <p>Estimado cliente,</p>
            <p>Le compartimos el reporte de visita correspondiente a la actividad realizada el {$data['activity_date']}.</p>
            <ul>
                <li><strong>Folio:</strong> {$data['folio']}</li>
                <li><strong>Empresa:</strong> {$data['company_name']}</li>
                <li><strong>Tipo de servicio:</strong> {$data['service_type']}</li>
                <li><strong>Horario:</strong> {$data['start_time']} - {$data['end_time']} ({$data['duration']} hrs)</li>
                <li><strong>Descripción:</strong> {$data['description']}</li>
            </ul>
            <p>Adjunto encontrará el reporte completo en formato PDF.</p>
            <p>Saludos,<br>{$lawyer->name}</p>
        ";

        return $this->mail->send($lawyer->email, $clientEmail, $subject, $bodyHtml, $attachments);
    }
}
