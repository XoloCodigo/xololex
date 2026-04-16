<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SharePointService
{
    protected string $tenantId;
    protected string $clientId;
    protected string $clientSecret;
    protected string $siteId;
    protected string $driveId;
    protected string $listId;

    public function __construct()
    {
        $this->tenantId = config('services.microsoft.tenant_id', '');
        $this->clientId = config('services.microsoft.client_id', '');
        $this->clientSecret = config('services.microsoft.client_secret', '');
        $this->siteId = config('services.microsoft.sharepoint_site_id', '');
        $this->driveId = config('services.microsoft.sharepoint_drive_id', '');
        $this->listId = config('services.microsoft.sharepoint_list_id', '');
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

            Log::error('SharePoint: Failed to get access token', ['response' => $response->body()]);
            return null;
        });
    }

    protected function graphUrl(string $path): string
    {
        return "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/{$path}";
    }

    public function insertListItem(array $fields): bool
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return false;
        }

        $response = Http::withToken($token)->post(
            $this->graphUrl("lists/{$this->listId}/items"),
            ['fields' => $fields]
        );

        if ($response->successful()) {
            return true;
        }

        Log::error('SharePoint: Failed to insert list item', [
            'status' => $response->status(),
            'response' => $response->body(),
        ]);
        return false;
    }

    public function uploadFile(string $localPath, string $fileName, string $folder = 'Prueba-coco1'): ?string
    {
        $token = $this->getAccessToken();
        if (! $token) {
            return null;
        }

        $fullPath = Storage::disk('public')->path($localPath);
        if (! file_exists($fullPath)) {
            Log::error('SharePoint: File not found', ['path' => $fullPath]);
            return null;
        }

        $fileContent = file_get_contents($fullPath);

        $response = Http::withToken($token)
            ->withHeaders(['Content-Type' => 'application/octet-stream'])
            ->withBody($fileContent, 'application/octet-stream')
            ->put($this->graphUrl("drive/root:/{$folder}/{$fileName}:/content"));

        if ($response->successful()) {
            return $response->json('webUrl');
        }

        Log::error('SharePoint: Failed to upload file', [
            'status' => $response->status(),
            'response' => $response->body(),
        ]);
        return null;
    }
}
