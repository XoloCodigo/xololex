<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AiFormatterService
{
    protected string $apiKey;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.api_key', '');
        $this->model = config('services.anthropic.model', 'claude-haiku-4-5-20251001');
    }

    public function formatReportField(string $fieldName, string $rawText): string
    {
        if (in_array(strtolower(trim($rawText)), ['ninguno', 'ninguna', 'no', 'n/a', 'na'])) {
            return $rawText;
        }

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->model,
            'max_tokens' => 500,
            'system' => 'Eres un formateador de texto legal. Tu ÚNICA tarea es tomar texto informal en español y devolverlo redactado de manera profesional para un reporte legal. REGLAS ESTRICTAS: 1) Solo devuelve el texto reformateado. 2) NUNCA hagas preguntas. 3) NUNCA des explicaciones. 4) NUNCA uses comillas. 5) Siempre responde en español. 6) Si el texto ya es profesional, devuélvelo tal cual. 7) Mantén el significado original.',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => "Reformatea este texto del campo \"{$fieldName}\" de un reporte de visita legal:\n\n{$rawText}",
                ],
            ],
        ]);

        if ($response->successful()) {
            $content = $response->json('content.0.text');
            return $content ?: $rawText;
        }

        return $rawText;
    }

    public function formatReport(array $data): array
    {
        $fieldsToFormat = [
            'visit_reason' => 'Motivo de la visita',
            'findings' => 'Hallazgos principales',
            'risks' => 'Riesgos detectados',
            'recommendations' => 'Recomendaciones',
            'observations' => 'Observaciones adicionales',
        ];

        foreach ($fieldsToFormat as $key => $label) {
            if (!empty($data[$key])) {
                $data[$key] = $this->formatReportField($label, $data[$key]);
            }
        }

        return $data;
    }
}
