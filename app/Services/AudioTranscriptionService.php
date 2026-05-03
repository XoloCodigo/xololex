<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AudioTranscriptionService
{
    protected string $openaiKey;
    protected string $wahaKey;

    public function __construct()
    {
        $this->openaiKey = (string) config('services.openai.api_key', '');
        $this->wahaKey = (string) config('services.waha.api_key', '');
    }

    public function transcribe(string $audioUrl): ?string
    {
        if (empty($this->openaiKey)) {
            Log::error('Whisper: OpenAI API key not configured');
            return null;
        }

        // Descargar audio desde WAHA
        $audioResponse = Http::withHeaders(['X-Api-Key' => $this->wahaKey])
            ->timeout(30)
            ->get($audioUrl);

        if (! $audioResponse->successful()) {
            Log::error('Whisper: Failed to download audio from WAHA', [
                'url' => $audioUrl,
                'status' => $audioResponse->status(),
            ]);
            return null;
        }

        // Guardar en archivo temporal
        $tempPath = tempnam(sys_get_temp_dir(), 'wahaaudio_') . '.ogg';
        file_put_contents($tempPath, $audioResponse->body());

        try {
            $response = Http::withToken($this->openaiKey)
                ->timeout(60)
                ->attach('file', file_get_contents($tempPath), 'audio.ogg')
                ->post('https://api.openai.com/v1/audio/transcriptions', [
                    'model' => 'whisper-1',
                    'language' => 'es',
                ]);

            if ($response->successful()) {
                return trim($response->json('text', ''));
            }

            Log::error('Whisper: Transcription failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }
}
