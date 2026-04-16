<?php

namespace App\Http\Controllers;

use App\Services\Bot\BotHandler;
use App\Services\WahaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handle(Request $request, BotHandler $handler)
    {
        $payload = $request->all();

        Log::error('WEBHOOK FULL PAYLOAD', ['payload' => json_encode($payload)]);

        if (($payload['event'] ?? '') !== 'message') {
            return response()->json(['ok' => true]);
        }

        if (($payload['payload']['fromMe'] ?? false) === true) {
            Log::error('WEBHOOK SKIPPED: fromMe');
            return response()->json(['ok' => true]);
        }

        $phone = WahaService::extractPhone($payload);
        $message = WahaService::extractMessage($payload);

        Log::error('WEBHOOK PROCESSING', ['phone' => $phone, 'message' => $message]);

        if (empty($phone) || empty($message)) {
            return response()->json(['ok' => true]);
        }

        $handler->handle($phone, $message);

        return response()->json(['ok' => true]);
    }
}
