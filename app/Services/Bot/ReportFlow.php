<?php

namespace App\Services\Bot;

use App\Models\Company;
use App\Models\Conversation;
use App\Models\Report;
use App\Services\ReportGeneratorService;
use App\Services\WahaService;

class ReportFlow
{
    public function __construct(
        protected WahaService $waha,
        protected ReportGeneratorService $generator,
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
        $companies = Company::where('is_active', true)->orderBy('name')->get();

        if ($companies->isEmpty()) {
            $this->waha->sendText($phone, "No hay empresas registradas. Contacta al administrador.");
            $conversation->reset();
            return;
        }

        $list = $companies->map(fn ($c, $i) => ($i + 1) . ". {$c->name}")->implode("\n");

        $this->waha->sendText($phone, "¿A qué empresa visitaste?\n\n{$list}\n\n_Escribe el número_");
        $conversation->setStep('report', 'receive_company', [
            'company_ids' => $companies->pluck('id')->toArray(),
        ]);
    }

    protected function receiveCompany(Conversation $conversation, string $phone, string $message): void
    {
        $index = (int) $message - 1;
        $companyIds = $conversation->data['company_ids'] ?? [];

        if (! isset($companyIds[$index])) {
            $this->waha->sendText($phone, "Opción no válida. Escribe el número de la empresa.");
            return;
        }

        $conversation->setStep('report', 'receive_visit_date', [
            'company_id' => $companyIds[$index],
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

        $this->waha->sendText($phone, "Generando tu reporte...");

        $report = Report::create([
            'folio' => Report::generateFolio(),
            'lawyer_id' => $conversation->lawyer_id,
            'company_id' => $data['company_id'],
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

        $doFormAfter = $data['do_form_after'] ?? false;

        if ($doFormAfter) {
            $conversation->update([
                'flow' => 'form',
                'step' => 'ask_service_type',
                'report_id' => $report->id,
                'data' => ['company_id' => $data['company_id'], 'report_id' => $report->id],
            ]);
            $this->waha->sendText($phone, "Reporte listo. Ahora vamos con el formulario de seguimiento.");
            app(FormFlow::class)->handle($conversation->fresh(), $phone, '');
        } else {
            $conversation->update(['report_id' => $report->id]);
            $conversation->reset();
            $this->waha->sendText($phone, "✓ Reporte {$report->folio} completado y listo.\n\n¿Necesitas algo más? Escribe \"hola\" para ver el menú.");
        }
    }
}
