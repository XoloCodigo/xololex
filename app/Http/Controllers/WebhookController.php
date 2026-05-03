<?php

namespace App\Http\Controllers;

use App\Services\AudioTranscriptionService;
use App\Services\Bot\BotHandler;
use App\Services\WahaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class WebhookController extends Controller
{
    public function handle(Request $request, BotHandler $handler, AudioTranscriptionService $audio, WahaService $waha)
    {
        $payload = $request->all();

        if (($payload['event'] ?? '') !== 'message') {
            return response()->json(['ok' => true]);
        }

        if (($payload['payload']['fromMe'] ?? false) === true) {
            return response()->json(['ok' => true]);
        }

        // Deduplicar mensajes por ID (300s para sobrevivir reintentos de WAHA)
        $messageId = $payload['payload']['id'] ?? null;
        if ($messageId && ! Cache::add("waha_msg:{$messageId}", true, 300)) {
            return response()->json(['ok' => true]);
        }

        $phone = WahaService::extractPhone($payload);
        if (empty($phone)) {
            return response()->json(['ok' => true]);
        }

        // Lock por teléfono: solo un webhook por número se procesa a la vez.
        // Esto previene reentradas durante operaciones lentas (transcripción + envío).
        $lock = Cache::lock("phone_lock:{$phone}", 90);
        if (! $lock->get()) {
            return response()->json(['ok' => true]);
        }

        try {
            $message = WahaService::extractMessage($payload);

            // Si es audio, transcribir
            if (WahaService::isAudioMessage($payload)) {
                $audioUrl = WahaService::extractAudioUrl($payload);
                if ($audioUrl) {
                    $waha->sendText($phone, "🎙 Transcribiendo tu audio...");
                    $transcribed = $audio->transcribe($audioUrl);
                    if ($transcribed) {
                        $message = $transcribed;
                    } else {
                        $waha->sendText($phone, "No pude transcribir el audio. Por favor, escribe tu respuesta.");
                        return response()->json(['ok' => true]);
                    }
                }
            }

            if (empty($message)) {
                return response()->json(['ok' => true]);
            }

            $handler->handle($phone, $message);
        } finally {
            optional($lock)->release();
        }

        return response()->json(['ok' => true]);
    }
}
