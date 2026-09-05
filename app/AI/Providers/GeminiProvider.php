<?php

namespace App\AI\Providers;

use App\AI\Contracts\AiProviderInterface;
use App\AI\DTOs\AiQueryIntent;
use App\AI\Enums\AiIntent;
use App\AI\Exceptions\AiProviderException;
use Illuminate\Support\Facades\Http;
use Throwable;

class GeminiProvider implements AiProviderInterface
{
    protected ?string $apiKey;
    protected string $model;
    protected string $baseUrl;
    protected int $timeout;

    public function __construct(
        ?string $apiKey = null,
        string $model = 'gemini-1.5-flash',
        string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta',
        int $timeout = 10
    ) {
        $this->apiKey = $apiKey ?? config('ai.providers.gemini.api_key');
        $this->model = $model ?? config('ai.providers.gemini.model', 'gemini-1.5-flash');
        $this->baseUrl = $baseUrl ?? config('ai.providers.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');
        $this->timeout = $timeout ?: (int) config('ai.timeout', 10);
    }

    /**
     * Extract structured intent and filters from user query using Gemini function calling.
     *
     * @param string $userQuery
     * @return AiQueryIntent
     * @throws AiProviderException
     */
    public function extractIntent(string $userQuery): AiQueryIntent
    {
        if (empty($this->apiKey) || $this->apiKey === 'tu_api_key_de_gemini_aqui') {
            throw new AiProviderException("La API Key de Gemini no está configurada en las variables de entorno.");
        }

        $url = "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}";

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $userQuery],
                    ],
                ],
            ],
            'tools' => [
                [
                    'function_declarations' => AiIntent::getFunctionDeclarations(),
                ],
            ],
            'tool_config' => [
                'function_calling_config' => [
                    'mode' => 'ANY',
                ],
            ],
            'system_instruction' => [
                'parts' => [
                    [
                        'text' => 'Eres un asistente especializado en ventas e inventario de un negocio. Tu único propósito es mapear la pregunta del usuario a una de las funciones de consulta disponibles con sus respectivos parámetros.',
                    ],
                ],
            ],
        ];

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, $payload);

            if (!$response->successful()) {
                $status = $response->status();
                $body = $response->json();
                $errorMessage = $body['error']['message'] ?? 'Error desconocido del proveedor de IA';

                throw new AiProviderException("Error del proveedor Gemini (HTTP {$status}): {$errorMessage}");
            }

            $json = $response->json();
            $candidates = $json['candidates'] ?? [];

            if (empty($candidates)) {
                throw new AiProviderException("El proveedor de IA no devolvió ninguna respuesta válida.");
            }

            $parts = $candidates[0]['content']['parts'] ?? [];

            foreach ($parts as $part) {
                if (isset($part['functionCall'])) {
                    $functionName = $part['functionCall']['name'] ?? '';
                    $arguments = $part['functionCall']['args'] ?? [];

                    return new AiQueryIntent(
                        intent: $functionName,
                        filters: (array) $arguments,
                        confidence: 1.0,
                        rawResponse: json_encode($part['functionCall'])
                    );
                }
            }

            throw new AiProviderException("No se detectó una intención estructurada en la respuesta del modelo.");
        } catch (AiProviderException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new AiProviderException("Falla en la comunicación con el proveedor de IA: {$e->getMessage()}", 0, $e);
        }
    }
}
