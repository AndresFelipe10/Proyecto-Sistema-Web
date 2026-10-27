<?php

namespace App\AI\Services;

use App\AI\DTOs\AiQueryIntent;
use App\AI\DTOs\AiQueryResult;
use App\AI\Enums\AiIntent;
use App\Models\Product;

class AiContextService
{
    public const MAX_HISTORY_TURNS = 5;

    /**
     * Get conversational context for a given tenant session.
     */
    public function getContext(int $businessId): array
    {
        return session()->get($this->getSessionKey($businessId), [
            'last_intent' => null,
            'last_domain' => null,
            'last_filters' => [],
            'conversation_history' => [],
        ]);
    }

    /**
     * Update conversational context after an intent has executed successfully.
     */
    public function updateContext(int $businessId, string $userQuery, AiQueryResult $result, array $filters = []): void
    {
        $context = $this->getContext($businessId);

        $domain = $result->meta['domain'] ?? $this->mapIntentToDomain($result->intent);

        $history = $context['conversation_history'] ?? [];
        $history[] = [
            'role' => 'user',
            'message' => $userQuery,
            'timestamp' => now()->toISOString(),
        ];
        $history[] = [
            'role' => 'assistant',
            'summary' => $result->summary,
            'intent' => $result->intent,
            'domain' => $domain,
            'timestamp' => now()->toISOString(),
        ];

        // Keep only last N turns (each turn has 2 messages: user and assistant)
        if (count($history) > (self::MAX_HISTORY_TURNS * 2)) {
            $history = array_slice($history, -(self::MAX_HISTORY_TURNS * 2));
        }

        session()->put($this->getSessionKey($businessId), [
            'last_intent' => $result->intent,
            'last_domain' => $domain,
            'last_filters' => $filters,
            'conversation_history' => $history,
        ]);
    }

    /**
     * Clear all conversational context for the tenant.
     */
    public function clearContext(int $businessId): void
    {
        session()->forget($this->getSessionKey($businessId));
    }

    /**
     * Normalize a text by converting to lowercase, removing punctuation and diacritics.
     */
    public function normalize(string $text): string
    {
        $lower = mb_strtolower(trim($text), 'UTF-8');
        // Replace diacritics
        $unaccented = strtr($lower, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n'
        ]);
        // Remove punctuation and extra whitespace
        $cleaned = preg_replace('/[¿?¡!.,;:_\-"\'()\/\\\\]+/', ' ', $unaccented);
        return trim(preg_replace('/\s+/', ' ', $cleaned));
    }

    /**
     * Check if normalized query matches follow-up listing or breakdown questions.
     */
    public function isFollowUpListingPattern(string $normalized): bool
    {
        $exactPatterns = [
            'cuales son',
            'quienes son',
            'muestramelos',
            'muestramelas',
            'mostrar lista',
            'dame la lista',
            'ver cuales',
            'cuales',
            'quienes',
            'listar',
            'dame los nombres',
            'ver detalle',
            'detallalos',
            'detallalas',
            'mostrar todos',
            'mostrar todas',
            'ver lista',
            'cuales serian',
            'cuales son ellos',
            'cuales son esos',
            'quienes son ellos',
            'cuales son los clientes',
            'quienes son los clientes',
            'mostrar clientes',
            'cuales son los productos',
            'dame el detalle',
        ];

        if (in_array($normalized, $exactPatterns, true)) {
            return true;
        }

        return (bool) preg_match('/^(y\s+)?(cuales(\s+son)?|quienes(\s+son)?|muestramelos?|muestramelas?|ver\s+(detalle|lista|cuales)|dame\s+(la\s+lista|los\s+nombres|el\s+detalle)|detallalos?)$/i', $normalized);
    }

    /**
     * Check if query is a temporal follow-up (e.g., "¿y ayer?", "¿y este mes?", "¿y hoy?").
     */
    public function detectTemporalPeriod(string $normalized): ?string
    {
        if (preg_match('/^(y\s+)?(ayer|el\s+dia\s+de\s+ayer)$/i', $normalized)) {
            return 'yesterday';
        }
        if (preg_match('/^(y\s+)?(hoy|el\s+dia\s+de\s+hoy)$/i', $normalized)) {
            return 'today';
        }
        if (preg_match('/^(y\s+)?(este\s+mes|el\s+mes(\s+actual)?|mes)$/i', $normalized)) {
            return 'month';
        }

        return null;
    }

    /**
     * Resolve follow-up query against previous conversational context.
     * Returns an AiQueryIntent if successfully resolved, or null if it's not a contextual follow-up.
     */
    public function resolveFollowUp(string $userQuery, int $businessId): ?AiQueryIntent
    {
        $context = $this->getContext($businessId);
        $lastDomain = $context['last_domain'] ?? null;
        $lastIntent = $context['last_intent'] ?? null;
        $lastFilters = $context['last_filters'] ?? [];

        if (empty($lastDomain) && empty($lastIntent)) {
            return null;
        }

        $normalized = $this->normalize($userQuery);

        // 1. Temporal sales follow-up: "¿y ayer?", "¿y este mes?", "¿y hoy?"
        $temporalPeriod = $this->detectTemporalPeriod($normalized);
        if ($temporalPeriod !== null && $lastDomain === 'sales') {
            return new AiQueryIntent(
                intent: AiIntent::SALES_SUMMARY,
                filters: ['period' => $temporalPeriod],
                confidence: 1.0,
                rawResponse: 'contextual_temporal_followup'
            );
        }

        // 2. Listing / breakdown follow-up: "¿cuáles son?", "¿quiénes son?", "muéstramelos", etc.
        if ($this->isFollowUpListingPattern($normalized)) {
            // Context: Customers
            if ($lastDomain === 'customers' || $lastIntent === AiIntent::COUNT_CUSTOMERS || $lastIntent === AiIntent::LIST_CUSTOMERS) {
                return new AiQueryIntent(
                    intent: AiIntent::LIST_CUSTOMERS,
                    filters: [],
                    confidence: 1.0,
                    rawResponse: 'contextual_customers_followup'
                );
            }

            // Context: Inventory / Stock
            if ($lastDomain === 'inventory' || in_array($lastIntent, [AiIntent::LOW_STOCK_PRODUCTS, AiIntent::OUT_OF_STOCK_PRODUCTS, AiIntent::INVENTORY_SUMMARY], true)) {
                if ($lastIntent === AiIntent::LOW_STOCK_PRODUCTS) {
                    return new AiQueryIntent(
                        intent: AiIntent::LOW_STOCK_PRODUCTS,
                        filters: [],
                        confidence: 1.0,
                        rawResponse: 'contextual_low_stock_followup'
                    );
                }

                if ($lastIntent === AiIntent::OUT_OF_STOCK_PRODUCTS) {
                    return new AiQueryIntent(
                        intent: AiIntent::OUT_OF_STOCK_PRODUCTS,
                        filters: [],
                        confidence: 1.0,
                        rawResponse: 'contextual_out_of_stock_followup'
                    );
                }

                if ($lastIntent === AiIntent::INVENTORY_SUMMARY) {
                    $hasLowStock = Product::where('business_id', $businessId)
                        ->where('is_active', true)
                        ->whereColumn('stock', '<=', 'min_stock')
                        ->exists();

                    return new AiQueryIntent(
                        intent: $hasLowStock ? AiIntent::LOW_STOCK_PRODUCTS : AiIntent::LIST_PRODUCTS,
                        filters: [],
                        confidence: 1.0,
                        rawResponse: 'contextual_inventory_summary_followup'
                    );
                }

                return new AiQueryIntent(
                    intent: AiIntent::OUT_OF_STOCK_PRODUCTS,
                    filters: [],
                    confidence: 1.0,
                    rawResponse: 'contextual_out_of_stock_followup'
                );
            }

            // Context: Sales
            if ($lastDomain === 'sales') {
                return new AiQueryIntent(
                    intent: AiIntent::SALES_BY_PERIOD,
                    filters: $lastFilters,
                    confidence: 1.0,
                    rawResponse: 'contextual_sales_followup'
                );
            }

            // Context: Tables
            if ($lastDomain === 'tables' || $lastIntent === AiIntent::RESTAURANT_TABLES_STATUS) {
                return new AiQueryIntent(
                    intent: AiIntent::RESTAURANT_TABLES_STATUS,
                    filters: [],
                    confidence: 1.0,
                    rawResponse: 'contextual_tables_followup'
                );
            }

            // Context: Restaurant open orders
            if ($lastDomain === 'restaurant_orders' || $lastIntent === AiIntent::RESTAURANT_ORDERS_OPEN) {
                return new AiQueryIntent(
                    intent: AiIntent::RESTAURANT_ORDERS_OPEN,
                    filters: [],
                    confidence: 1.0,
                    rawResponse: 'contextual_orders_followup'
                );
            }

            // Context: Deliveries
            if ($lastDomain === 'deliveries' || $lastIntent === AiIntent::RESTAURANT_DELIVERIES_TODAY) {
                return new AiQueryIntent(
                    intent: AiIntent::RESTAURANT_DELIVERIES_TODAY,
                    filters: [],
                    confidence: 1.0,
                    rawResponse: 'contextual_deliveries_followup'
                );
            }

            // Context: Suppliers
            if ($lastDomain === 'suppliers' || $lastIntent === AiIntent::LIST_SUPPLIERS) {
                return new AiQueryIntent(
                    intent: AiIntent::LIST_SUPPLIERS,
                    filters: [],
                    confidence: 1.0,
                    rawResponse: 'contextual_suppliers_followup'
                );
            }

            // Context: Expenses
            if ($lastDomain === 'expenses' || $lastIntent === AiIntent::EXPENSES_SUMMARY) {
                return new AiQueryIntent(
                    intent: AiIntent::EXPENSES_SUMMARY,
                    filters: [],
                    confidence: 1.0,
                    rawResponse: 'contextual_expenses_followup'
                );
            }

            // Context: Products
            if ($lastDomain === 'products' || $lastIntent === AiIntent::LIST_PRODUCTS) {
                return new AiQueryIntent(
                    intent: AiIntent::LIST_PRODUCTS,
                    filters: [],
                    confidence: 1.0,
                    rawResponse: 'contextual_products_followup'
                );
            }
        }

        return null;
    }

    /**
     * Map intent to semantic domain.
     */
    public function mapIntentToDomain(?string $intent): string
    {
        return match ($intent) {
            AiIntent::LIST_CUSTOMERS, AiIntent::COUNT_CUSTOMERS => 'customers',
            AiIntent::LIST_PRODUCTS => 'products',
            AiIntent::LOW_STOCK_PRODUCTS, AiIntent::OUT_OF_STOCK_PRODUCTS, AiIntent::INVENTORY_SUMMARY => 'inventory',
            AiIntent::SALES_SUMMARY, AiIntent::SALES_BY_PERIOD, AiIntent::TOP_SELLING_PRODUCTS => 'sales',
            AiIntent::LIST_SUPPLIERS => 'suppliers',
            AiIntent::EXPENSES_SUMMARY => 'expenses',
            AiIntent::RESTAURANT_TABLES_STATUS => 'tables',
            AiIntent::RESTAURANT_ORDERS_OPEN => 'restaurant_orders',
            AiIntent::RESTAURANT_DELIVERIES_TODAY => 'deliveries',
            AiIntent::RESTAURANT_TIPS_COLLECTED => 'tips',
            default => 'general',
        };
    }

    /**
     * Build informative guidance message when intent is unrecognized, customized by business type.
     */
    public function getUnrecognizedGuidance(?string $businessType): string
    {
        if ($businessType === 'restaurant') {
            return 'No pude identificar tu consulta. Como restaurante, puedes preguntarme sobre: mesas libres u ocupadas, comandas abiertas, domicilios de hoy, propinas recaudadas, platos o menú, clientes y balance de ventas.';
        }

        return 'No pude identificar tu consulta. En tu comercio puedes preguntarme sobre: existencias y stock bajo, productos agotados, balance de ventas de hoy o del mes, productos más vendidos, clientes registrados, proveedores y gastos.';
    }

    protected function getSessionKey(int $businessId): string
    {
        return "ai_context_{$businessId}";
    }
}
