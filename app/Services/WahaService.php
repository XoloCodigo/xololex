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
        return str_replace('@c.us', '', $payload['payload']['from'] ?? '');
    }

    public static function extractMessage(array $payload): string
    {
        return trim($payload['payload']['body'] ?? '');
    }
}
