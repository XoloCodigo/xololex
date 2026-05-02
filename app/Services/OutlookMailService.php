<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OutlookMailService
{
    protected string $tenantId;
    protected string $clientId;
    protected string $clientSecret;

    public function __construct()
    {
        $this->tenantId = config('services.microsoft.tenant_id', '');
        $this->clientId = config('services.microsoft.client_id', '');
        $this->clientSecret = config('services.microsoft.client_secret', '');
    }

    protected function getAccessToken(): ?string
    {
        return Cache::remember('sharepoint_token', 3500, function () {
            $response = Http::asForm()->post(
                "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token",
                [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope' => 'https://graph.microsoft.com/.default',
                    'grant_type' => 'client_credentials',
                ]
            );

            if ($response->successful()) {
                return $response->json('access_token');
            }

            Log::error('Outlook: Failed to get access token', ['response' => $response->body()]);
            return null;
        });
    }

    public function send(string $fromUser, string $toEmail, string $subject, string $bodyHtml, array $attachments = []): bool
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return false;
        }

        $message = [
            'message' => [
                'subject' => $subject,
                'body' => [
                    'contentType' => 'HTML',
                    'content' => $bodyHtml,
                ],
                'toRecipients' => [
                    ['emailAddress' => ['address' => $toEmail]],
                ],
            ],
            'saveToSentItems' => true,
        ];

        if (! empty($attachments)) {
            $message['message']['attachments'] = array_map(function ($att) {
                $content = '';
                if (isset($att['storage_path'])) {
                    $fullPath = Storage::disk('public')->path($att['storage_path']);
                    if (file_exists($fullPath)) {
                        $content = base64_encode(file_get_contents($fullPath));
                    }
                }
                return [
                    '@odata.type' => '#microsoft.graph.fileAttachment',
                    'name' => $att['name'],
                    'contentType' => $att['contentType'] ?? 'application/octet-stream',
                    'contentBytes' => $content,
                ];
            }, $attachments);
        }

        $response = Http::withToken($token)
            ->timeout(30)
            ->post(
                "https://graph.microsoft.com/v1.0/users/{$fromUser}/sendMail",
                $message
            );

        if ($response->successful()) {
            return true;
        }

        Log::error('Outlook: Failed to send mail', [
            'from' => $fromUser,
            'to' => $toEmail,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
        return false;
    }
}
