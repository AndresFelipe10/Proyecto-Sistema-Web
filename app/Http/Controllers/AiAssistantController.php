<?php

namespace App\Http\Controllers;

use App\AI\Services\AiQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiAssistantController extends Controller
{
    /**
     * Show the natural language query assistant interface.
     */
    public function index(): View
    {
        $suggestions = [
            '¿Qué productos tienen bajo stock o requieren reposición?',
            '¿Cuáles son los productos completamente agotados?',
            '¿Cuáles son los productos más vendidos?',
            'Resumen de ventas de hoy',
            '¿Cuántos clientes activos tenemos registrados?',
            '¿Cuál es el valor total de nuestro inventario?',
            'Listar los primeros productos del catálogo',
        ];

        return view('ai.index', [
            'enabled' => (bool) config('ai.enabled', false),
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * Process a natural language question and return structured response.
     */
    public function ask(Request $request, AiQueryService $aiQueryService): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|max:500',
        ]);

        $businessId = (int) (session('current_business_id') ?? $request->user()->current_business_id);

        $result = $aiQueryService->query($validated['query'], $businessId);

        return response()->json($result->toArray());
    }
}
