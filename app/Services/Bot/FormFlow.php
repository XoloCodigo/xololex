<?php

namespace App\Services\Bot;

use App\Models\Conversation;
use App\Models\Report;
use App\Models\SharepointForm;
use App\Services\WahaService;

class FormFlow
{
    public function __construct(
        protected WahaService $waha,
    ) {}

    public function handle(Conversation $conversation, string $phone, string $message): void
    {
        match ($conversation->step) {
            'ask_report' => $this->askReport($conversation, $phone),
            'receive_report' => $this->receiveReport($conversation, $phone, $message),
            'ask_service_type' => $this->askServiceType($conversation, $phone),
            'receive_service_type' => $this->receiveServiceType($conversation, $phone, $message),
            'receive_hours' => $this->receiveHours($conversation, $phone, $message),
            'receive_urgency' => $this->receiveUrgency($conversation, $phone, $message),
            'receive_followup' => $this->receiveFollowup($conversation, $phone, $message),
            'receive_followup_date' => $this->receiveFollowupDate($conversation, $phone, $message),
            'receive_notes' => $this->receiveNotes($conversation, $phone, $message),
            default => $this->askReport($conversation, $phone),
        };
    }

    protected function askReport(Conversation $conversation, string $phone): void
    {
        $reports = Report::where('lawyer_id', $conversation->lawyer_id)
            ->whereDoesntHave('sharepointForm')
            ->latest()
            ->take(10)
            ->get();

        if ($reports->isEmpty()) {
            $this->waha->sendText($phone, "No tienes reportes pendientes de formulario. Primero crea un reporte.");
            $conversation->reset();
            return;
        }

        $list = $reports->map(fn ($r, $i) => ($i + 1) . ". {$r->folio} — {$r->company->name} ({$r->visit_date->format('d/m/Y')})")->implode("\n");

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
        $conversation->setStep('form', 'ask_service_type', [
            'report_id' => $report->id,
            'company_id' => $report->company_id,
        ]);
        $conversation->update(['report_id' => $report->id]);

        $this->askServiceType($conversation->fresh(), $phone);
    }

    protected function askServiceType(Conversation $conversation, string $phone): void
    {
        $this->waha->sendText($phone, "Tipo de servicio realizado:\n\n1. Auditoría\n2. Consultoría\n3. Revisión documental\n4. Capacitación\n5. Otro\n\n_Escribe el número_");
        $conversation->setStep('form', 'receive_service_type');
    }

    protected function receiveServiceType(Conversation $conversation, string $phone, string $message): void
    {
        $types = [1 => 'auditoria', 2 => 'consultoria', 3 => 'revision_documental', 4 => 'capacitacion', 5 => 'otro'];
        $option = (int) $message;

        if (! isset($types[$option])) {
            $this->waha->sendText($phone, "Opción no válida. Escribe un número del 1 al 5.");
            return;
        }

        $conversation->setStep('form', 'receive_hours', ['service_type' => $types[$option]]);
        $this->waha->sendText($phone, "¿Cuántas horas dedicaste? (ej: 2.5)");
    }

    protected function receiveHours(Conversation $conversation, string $phone, string $message): void
    {
        $hours = str_replace(',', '.', $message);

        if (! is_numeric($hours) || $hours <= 0) {
            $this->waha->sendText($phone, "Escribe un número válido de horas (ej: 3 o 2.5)");
            return;
        }

        $conversation->setStep('form', 'receive_urgency', ['hours_spent' => (float) $hours]);
        $this->waha->sendText($phone, "Nivel de urgencia de los hallazgos:\n\n1. Bajo\n2. Medio\n3. Alto\n4. Crítico\n\n_Escribe el número_");
    }

    protected function receiveUrgency(Conversation $conversation, string $phone, string $message): void
    {
        $levels = [1 => 'bajo', 2 => 'medio', 3 => 'alto', 4 => 'critico'];
        $option = (int) $message;

        if (! isset($levels[$option])) {
            $this->waha->sendText($phone, "Opción no válida. Escribe un número del 1 al 4.");
            return;
        }

        $conversation->setStep('form', 'receive_followup', ['urgency_level' => $levels[$option]]);
        $this->waha->sendText($phone, "¿Requiere visita de seguimiento?\n\n1. Sí\n2. No");
    }

    protected function receiveFollowup(Conversation $conversation, string $phone, string $message): void
    {
        $option = strtolower(trim($message));

        if (in_array($option, ['1', 'sí', 'si'])) {
            $conversation->setStep('form', 'receive_followup_date', ['requires_followup' => true]);
            $this->waha->sendText($phone, "¿Para cuándo? (dd/mm/aaaa)");
        } elseif (in_array($option, ['2', 'no'])) {
            $conversation->setStep('form', 'receive_notes', ['requires_followup' => false]);
            $this->waha->sendText($phone, "¿Notas adicionales? (escribe \"ninguna\" si no hay)");
        } else {
            $this->waha->sendText($phone, "Escribe 1 (Sí) o 2 (No).");
        }
    }

    protected function receiveFollowupDate(Conversation $conversation, string $phone, string $message): void
    {
        try {
            $date = \Carbon\Carbon::createFromFormat('d/m/Y', $message)->format('Y-m-d');
        } catch (\Exception $e) {
            $this->waha->sendText($phone, "Formato no válido. Usa dd/mm/aaaa.");
            return;
        }

        $conversation->setStep('form', 'receive_notes', ['followup_date' => $date]);
        $this->waha->sendText($phone, "¿Notas adicionales? (escribe \"ninguna\" si no hay)");
    }

    protected function receiveNotes(Conversation $conversation, string $phone, string $message): void
    {
        $data = array_merge($conversation->data ?? [], [
            'additional_notes' => strtolower($message) === 'ninguna' ? null : $message,
        ]);

        $form = SharepointForm::create([
            'report_id' => $data['report_id'],
            'lawyer_id' => $conversation->lawyer_id,
            'company_id' => $data['company_id'],
            'service_type' => $data['service_type'],
            'hours_spent' => $data['hours_spent'],
            'urgency_level' => $data['urgency_level'],
            'requires_followup' => $data['requires_followup'] ?? false,
            'followup_date' => $data['followup_date'] ?? null,
            'additional_notes' => $data['additional_notes'],
            'status' => 'completed',
        ]);

        $conversation->reset();

        $report = Report::find($data['report_id']);

        $this->waha->sendText($phone, "✓ Formulario completado para reporte {$report->folio}\n\n*Resumen:*\n• Servicio: {$data['service_type']}\n• Horas: {$data['hours_spent']}\n• Urgencia: {$data['urgency_level']}\n• Seguimiento: " . ($data['requires_followup'] ? 'Sí' : 'No') . "\n\n¿Necesitas algo más? Escribe \"hola\" para ver el menú.");
    }
}
