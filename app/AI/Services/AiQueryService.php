<?php

namespace App\AI\Services;

use App\AI\Contracts\AiProviderInterface;
use App\AI\DTOs\AiQueryResult;
use App\AI\Enums\AiIntent;
use App\AI\Exceptions\AiProviderException;
use App\AI\Tools\CustomerAiTools;
use App\AI\Tools\InventoryAiTools;
use App\AI\Tools\ProductAiTools;
use App\AI\Tools\SaleAiTools;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiQueryService
{
    public const FALLBACK_MESSAGE = 'El asistente de consultas no está disponible temporalmente. Puedes utilizar los módulos tradicionales de clientes, ventas e inventario.';

    public function __construct(
        protected AiProviderInterface $provider,
        protected CustomerAiTools $customerTools,
        protected ProductAiTools $productTools,
        protected InventoryAiTools $inventoryTools,
        protected SaleAiTools $saleTools
    ) {
    }

    /**
     * Process a natural language query with strict whitelist, read-only enforcement, and tenant isolation.
     *
     * @param string $userQuery
     * @param int $businessId
     * @return AiQueryResult
     */
    public function query(string $userQuery, int $businessId): AiQueryResult
    {
        // 1. Verificación de módulo activado en .env
        if (!config('ai.enabled', false)) {
            return new AiQueryResult(
                intent: 'disabled',
                summary: 'El módulo de IA está desactivado. Puedes habilitarlo configurando AI_MODULE_ENABLED=true en el archivo .env.',
                data: [],
                meta: ['enabled' => false],
                isSuccess: false
            );
        }

        $cleanQuery = trim($userQuery);
        if (empty($cleanQuery)) {
            return new AiQueryResult(
                intent: 'empty',
                summary: 'Por favor, ingresa una pregunta o consulta sobre tu negocio.',
                data: [],
                isSuccess: false
            );
        }

        // 2. Extracción de intención mediante el proveedor (con captura de timeouts/errores)
        try {
            $extractedIntent = $this->provider->extractIntent($cleanQuery);
        } catch (AiProviderException | Throwable $e) {
            // Sanitizar mensaje eliminando query strings, URLs y tokens
            $sanitizedMessage = preg_replace('/(\?|&)(key|apiKey|api_key|token)=[^&\s]+/i', '$1$2=[REDACTED]', $e->getMessage());
            $sanitizedMessage = preg_replace('/https?:\/\/[^\s]+/i', '[URL]', $sanitizedMessage);

            Log::warning("Falla en proveedor de IA: {$sanitizedMessage}", [
                'business_id' => $businessId,
                'query' => $cleanQuery,
            ]);

            return new AiQueryResult(
                intent: 'fallback',
                summary: self::FALLBACK_MESSAGE,
                data: [],
                meta: ['error' => 'provider_unavailable'],
                isSuccess: false
            );
        }

        $intent = $extractedIntent->intent;
        $filters = $extractedIntent->filters;

        // 3. Verificación estricta contra Whitelist inmutable
        if (!AiIntent::isValid($intent)) {
            return new AiQueryResult(
                intent: 'unauthorized',
                summary: 'La consulta solicitada no corresponde a una operación de lectura permitida. Puedo ayudarte con información sobre stock, clientes, ventas y productos de tu negocio.',
                data: [],
                meta: ['attempted_intent' => $intent],
                isSuccess: false
            );
        }

        // 4. Despacho a herramienta de solo lectura inyectando FORZOSAMENTE el $businessId de Laravel
        return match ($intent) {
            AiIntent::LIST_CUSTOMERS => $this->customerTools->list($businessId, $filters),
            AiIntent::COUNT_CUSTOMERS => $this->customerTools->count($businessId),
            AiIntent::LIST_PRODUCTS => $this->productTools->list($businessId, $filters),
            AiIntent::LOW_STOCK_PRODUCTS => $this->inventoryTools->lowStock($businessId),
            AiIntent::OUT_OF_STOCK_PRODUCTS => $this->inventoryTools->outOfStock($businessId),
            AiIntent::INVENTORY_SUMMARY => $this->inventoryTools->summary($businessId),
            AiIntent::SALES_SUMMARY => $this->saleTools->summary($businessId, $filters),
            AiIntent::SALES_BY_PERIOD => $this->saleTools->byPeriod($businessId, $filters),
            AiIntent::TOP_SELLING_PRODUCTS => $this->saleTools->topSelling($businessId, $filters),
        };
    }
}
