<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WahaService
{
    protected string $baseUrl;
    protected string $session;
    protected string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.waha.url'), '/');
        $this->session = config('services.waha.session', 'default');
        $this->apiKey = config('services.waha.api_key', '');
    }

    protected function http(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders(['X-Api-Key' => $this->apiKey]);
    }

    public function sendText(string $phone, string $message): void
    {
        $this->http()->post("{$this->baseUrl}/api/sendText", [
            'chatId' => $this->formatChatId($phone),
            'text' => $message,
            'session' => $this->session,
        ]);
    }

    public function sendDocument(string $phone, string $filePath, string $filename, string $caption = ''): void
    {
        $this->http()->post("{$this->baseUrl}/api/sendFile", [
            'chatId' => $this->formatChatId($phone),
            'file' => [
                'url' => $filePath,
                'filename' => $filename,
            ],
            'caption' => $caption,
            'session' => $this->session,
        ]);
    }

    public function sendButtons(string $phone, string $body, array $buttons): void
    {
        $this->http()->post("{$this->baseUrl}/api/sendText", [
            'chatId' => $this->formatChatId($phone),
            'text' => $body . "\n\n" . collect($buttons)->map(fn ($b, $i) => ($i + 1) . ". {$b}")->implode("\n"),
            'session' => $this->session,
        ]);
    }

    protected function formatChatId(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);

        return str_ends_with($phone, '@c.us') ? $phone : "{$phone}@c.us";
    }

    public static function extractPhone(array $payload): string
    {
        $from = $payload['payload']['from'] ?? '';

        // Si viene en formato LID, resolver via API de WAHA
        if (str_contains($from, '@lid')) {
            $contact = self::resolveContact($from);
            if ($contact) {
                return $contact;
            }
        }

        return str_replace('@c.us', '', $from);
    }

    protected static function resolveContact(string $lid): ?string
    {
        $baseUrl = rtrim(config('services.waha.url'), '/');
        $apiKey = config('services.waha.api_key', '');
        $session = config('services.waha.session', 'default');

        try {
            $response = Http::withHeaders(['X-Api-Key' => $apiKey])
                ->get("{$baseUrl}/api/contacts", [
                    'session' => $session,
                    'contactId' => $lid,
                ]);

            if ($response->successful()) {
                $number = $response->json('number');
                if ($number) {
                    return $number;
                }
            }
        } catch (\Throwable $e) {
            // Fall through
        }

        return null;
    }

    public static function extractMessage(array $payload): string
    {
        return trim($payload['payload']['body'] ?? '');
    }
}
