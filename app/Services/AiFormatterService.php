<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiFormatterService
{
    protected string $apiKey;
    protected string $model;

    protected const FIELDS_TO_FORMAT = [
        'visit_reason' => 'Motivo de la visita',
        'findings' => 'Hallazgos principales',
        'risks' => 'Riesgos detectados',
        'recommendations' => 'Recomendaciones',
        'observations' => 'Observaciones adicionales',
    ];

    protected const SYSTEM_PROMPT = 'Eres un formateador de texto legal. Tu ÚNICA tarea es tomar texto informal en español y devolverlo redactado de manera profesional para un reporte legal. REGLAS ESTRICTAS: 1) Solo devuelve el texto reformateado. 2) NUNCA hagas preguntas. 3) NUNCA des explicaciones. 4) NUNCA uses comillas. 5) Siempre responde en español. 6) Si el texto ya es profesional, devuélvelo tal cual. 7) Mantén el significado original.';

    protected const SKIP_VALUES = ['ninguno', 'ninguna', 'no', 'n/a', 'na'];

    public function __construct()
    {
        $this->apiKey = (string) config('services.anthropic.api_key', '');
        $this->model = (string) config('services.anthropic.model', 'claude-haiku-4-5-20251001');
    }

    public function formatReport(array $data): array
    {
        if (empty($this->apiKey)) {
            return $data;
        }

        $toFormat = [];
        foreach (self::FIELDS_TO_FORMAT as $key => $label) {
            $value = $data[$key] ?? '';
            if ($value === '' || in_array(strtolower(trim($value)), self::SKIP_VALUES, true)) {
                continue;
            }
            $toFormat[$key] = ['label' => $label, 'value' => $value];
        }

        if (empty($toFormat)) {
            return $data;
        }

        // Llamadas paralelas: 5 campos × 1-2s en paralelo ≈ 2s total en lugar de 5-10s
        $responses = Http::pool(function ($pool) use ($toFormat) {
            $requests = [];
            foreach ($toFormat as $key => $field) {
                $requests[] = $pool->as($key)
                    ->withHeaders([
                        'x-api-key' => $this->apiKey,
                        'anthropic-version' => '2023-06-01',
                        'content-type' => 'application/json',
                    ])
                    ->timeout(15)
                    ->post('https://api.anthropic.com/v1/messages', [
                        'model' => $this->model,
                        'max_tokens' => 500,
                        'system' => self::SYSTEM_PROMPT,
                        'messages' => [
                            [
                                'role' => 'user',
                                'content' => "Reformatea este texto del campo \"{$field['label']}\" de un reporte de visita legal:\n\n{$field['value']}",
                            ],
                        ],
                    ]);
            }
            return $requests;
        });

        foreach ($toFormat as $key => $field) {
            $response = $responses[$key] ?? null;
            if ($response && $response->successful()) {
                $content = $response->json('content.0.text');
                if (!empty($content)) {
                    $data[$key] = $content;
                }
            } else {
                Log::warning('AI formatter: field not formatted, using original', [
                    'field' => $key,
                    'status' => $response?->status(),
                ]);
            }
        }

        return $data;
    }
}
