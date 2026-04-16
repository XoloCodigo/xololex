<?php

namespace App\Http\Controllers;

use App\Services\Bot\BotHandler;
use App\Services\WahaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class WebhookController extends Controller
{
    public function handle(Request $request, BotHandler $handler)
    {
        $payload = $request->all();

        if (($payload['event'] ?? '') !== 'message') {
            return response()->json(['ok' => true]);
        }

        if (($payload['payload']['fromMe'] ?? false) === true) {
            return response()->json(['ok' => true]);
        }

        // Deduplicar mensajes por ID
        $messageId = $payload['payload']['id'] ?? null;
        if ($messageId && ! Cache::add("waha_msg:{$messageId}", true, 60)) {
            return response()->json(['ok' => true]);
        }

        $phone = WahaService::extractPhone($payload);
        $message = WahaService::extractMessage($payload);

        if (empty($phone) || empty($message)) {
            return response()->json(['ok' => true]);
        }

        $handler->handle($phone, $message);

        return response()->json(['ok' => true]);
    }
}
