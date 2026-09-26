<?php

namespace Tests\Feature\Security;

use App\AI\Contracts\AiProviderInterface;
use App\AI\Exceptions\AiProviderException;
use App\AI\Providers\GeminiProvider;
use App\AI\Services\AiQueryService;
use App\Models\Business;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiApiKeyLogSanitizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_gemini_provider_sends_key_in_header_not_in_url(): void
    {
        $testApiKey = 'TEST_SECRET_API_KEY_HEADER_VALIDATION';
        config(['services.gemini.api_key' => $testApiKey]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'functionCall' => [
                                        'name' => 'sales_total_today',
                                        'args' => [],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $provider = new GeminiProvider(apiKey: $testApiKey);
        $provider->extractIntent('¿Cuánto vendimos hoy?');

        Http::assertSent(function ($request) use ($testApiKey) {
            // URL MUST NOT contain key parameter
            $urlHasKey = str_contains($request->url(), 'key=');
            // Header MUST contain the key
            $hasKeyHeader = $request->hasHeader('x-goog-api-key', $testApiKey);

            return ! $urlHasKey && $hasKeyHeader;
        });
    }

    public function test_ai_query_service_sanitizes_exceptions_and_never_logs_api_key(): void
    {
        $logPath = storage_path('logs/laravel.log');
        $initialLogContent = File::exists($logPath) ? File::get($logPath) : '';

        $fakeSecretKey = 'SUPER_SECRET_LEAKED_API_KEY_98765';

        // Mock provider that throws exception with URL containing key
        $mockProvider = $this->createMock(AiProviderInterface::class);
        $mockProvider->method('extractIntent')
            ->willThrowException(new \RuntimeException("Connection failed to https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$fakeSecretKey}"));

        $this->app->instance(AiProviderInterface::class, $mockProvider);

        $service = $this->app->make(AiQueryService::class);
        $result = $service->query('¿Cuánto vendimos hoy?', 1);

        $this->assertFalse($result->isSuccess);

        $finalLogContent = File::exists($logPath) ? File::get($logPath) : '';
        $newLogEntries = substr($finalLogContent, strlen($initialLogContent));

        $this->assertStringNotContainsString($fakeSecretKey, $newLogEntries);
        $this->assertStringNotContainsString("key={$fakeSecretKey}", $newLogEntries);
    }
}
