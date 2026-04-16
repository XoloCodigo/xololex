<?php

namespace App\Services\Bot;

use App\Models\Conversation;
use App\Models\Lawyer;
use App\Services\WahaService;

class BotHandler
{
    public function __construct(
        protected WahaService $waha,
        protected ReportFlow $reportFlow,
        protected FormFlow $formFlow,
    ) {}

    public function handle(string $phone, string $message): void
    {
        $lawyer = Lawyer::where('phone', $phone)->first();

        if (! $lawyer) {
            $this->waha->sendText($phone, "No estás registrado en el sistema. Contacta al administrador.");
            return;
        }

        if (! $lawyer->is_active) {
            $this->waha->sendText($phone, "Tu cuenta está desactivada. Contacta al administrador.");
            return;
        }

        $conversation = Conversation::firstOrCreate(
            ['lawyer_id' => $lawyer->id],
            ['phone' => $phone, 'flow' => 'idle', 'step' => 'start']
        );

        if ($this->isCancel($message)) {
            $conversation->reset();
            $this->waha->sendText($phone, "Operación cancelada. ¿En qué te puedo ayudar?");
            $this->sendMenu($phone, $lawyer->name);
            return;
        }

        match ($conversation->flow) {
            'idle' => $this->handleMenu($conversation, $phone, $message, $lawyer),
            'report' => $this->reportFlow->handle($conversation, $phone, $message),
            'form' => $this->formFlow->handle($conversation, $phone, $message),
            default => $this->handleMenu($conversation, $phone, $message, $lawyer),
        };
    }

    protected function handleMenu(Conversation $conversation, string $phone, string $message, Lawyer $lawyer): void
    {
        $option = trim($message);

        if (in_array($option, ['1', 'reporte'])) {
            $conversation->setStep('report', 'ask_company');
            $this->reportFlow->handle($conversation, $phone, $message);
            return;
        }

        if (in_array($option, ['2', 'formulario'])) {
            $conversation->setStep('form', 'ask_report');
            $this->formFlow->handle($conversation, $phone, $message);
            return;
        }

        $this->sendWelcome($phone, $lawyer->name);
    }

    protected function sendWelcome(string $phone, string $name): void
    {
        $this->waha->sendText($phone, "¡Hola {$name}! 👋 Soy *XoloLex*, tu asistente legal.\n\nTe ayudo a:\n• *Generar reportes de visita* — Te guío con preguntas y genero el Word/PDF automáticamente.\n• *Llenar formularios de seguimiento* — Registro tu actividad directo en SharePoint.\n\nEscribe *\"cancelar\"* o *\"0\"* en cualquier momento para reiniciar.");
        $this->sendMenu($phone, $name);
    }

    protected function sendMenu(string $phone, string $name = ''): void
    {
        $this->waha->sendText($phone, "¿Qué quieres hacer?\n\n1. Hacer reporte de visita\n2. Llenar formulario de seguimiento\n\n_Escribe el número o la opción_");
    }

    protected function isCancel(string $message): bool
    {
        return in_array(strtolower($message), ['cancelar', 'salir', 'cancel', '0']);
    }
}
