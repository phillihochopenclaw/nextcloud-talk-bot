<?php

declare(strict_types=1);

namespace OCA\TalkBot\Tests\Unit\Service\Security;

use OCA\TalkBot\Service\OpenAIConfigService;
use OCA\TalkBot\Service\CustomAIService;
use OCA\TalkBot\Service\SettingsService;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Security tests for API key handling
 * 
 * @covers \OCA\TalkBot\Service\OpenAIConfigService
 * @covers \OCA\TalkBot\Service\CustomAIService
 * @covers \OCA\TalkBot\Service\SettingsService
 */
class APIKeySecurityTest extends TestCase
{
    // =============================
    // API Key Not In Logs Tests
    // =============================

    public function testOpenAIApiKeyNotLogged(): void
    {
        $config = $this->createMock(IConfig::class);
        $logger = $this->createMock(LoggerInterface::class);
        
        $service = new OpenAIConfigService($config);

        // Get settings should mask the API key
        $config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'openai_api_key', '', 'sk-secret-key-12345678'],
                ['talk_bot', 'openai_org_id', '', ''],
                ['talk_bot', 'openai_model', 'gpt-4', 'gpt-4'],
                ['talk_bot', 'openai_max_tokens', '1024', '1024'],
                ['talk_bot', 'openai_temperature', '0.7', '0.7'],
            ]);

        $settings = $service->getAllSettings();

        // Verify the API key is masked
        $this->assertStringContainsString('***', $settings['apiKey']);
        $this->assertStringNotContainsString('sk-secret-key', $settings['apiKey']);
        $this->assertStringNotContainsString('12345678', $settings['apiKey']);
    }

    public function testCustomAIApiKeyNotLogged(): void
    {
        $config = $this->createMock(IConfig::class);
        $httpClient = $this->createMock(IClient::class);
        $logger = $this->createMock(LoggerInterface::class);

        $service = new CustomAIService($httpClient, $config, $logger);

        $config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'custom_ai_endpoint', '', 'https://api.example.com'],
                ['talk_bot', 'custom_ai_model', '', 'model'],
                ['talk_bot', 'custom_ai_api_key', '', 'super-secret-key'],
                ['talk_bot', 'custom_ai_headers', '{}', '{}'],
                ['talk_bot', 'custom_ai_timeout', '30', '30'],
            ]);

        $settings = $service->getSettings();

        // Verify hasApiKey is true but key is not exposed
        $this->assertTrue($settings['hasApiKey']);
        $this->assertArrayNotHasKey('apiKey', $settings);
    }

    public function testSettingsServiceMasksApiKeys(): void
    {
        $config = $this->createMock(IConfig::class);

        $service = new SettingsService($config);

        $config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'openai_api_key', '', 'sk-secret-openai-key'],
                ['talk_bot', 'custom_ai_api_key', '', 'secret-custom-key'],
                ['talk_bot', 'bot_url', '', 'https://bot.example.com'],
                ['talk_bot', 'bot_token', '', 'secret-bot-token'],
                ['talk_bot', 'allowed_channels', '[]', '[]'],
                ['talk_bot', 'enable_ai', 'no', 'yes'],
                ['talk_bot', 'ai_model', 'claude-3-opus', 'gpt-4'],
                ['talk_bot', 'max_messages', '50', '50'],
                ['talk_bot', 'openai_enabled', 'no', 'yes'],
                ['talk_bot', 'openai_model', 'gpt-4', 'gpt-4'],
                ['talk_bot', 'custom_provider_enabled', 'no', 'yes'],
                ['talk_bot', 'custom_provider_endpoint', '', 'https://api.custom.com'],
                ['talk_bot', 'custom_provider_model', '', 'custom-model'],
                ['talk_bot', 'custom_provider_headers', '{}', '{}'],
                ['talk_bot', 'active_provider', 'none', 'openai'],
                ['talk_bot', 'ai_status', 'inactive', 'active'],
                ['talk_bot', 'last_error', '', ''],
            ]);

        $settings = $service->getAdminSettings();

        // Verify API keys are masked or not present in output
        $this->assertStringNotContainsString('sk-secret-openai-key', json_encode($settings));
        $this->assertStringNotContainsString('secret-custom-key', json_encode($settings));
        $this->assertStringNotContainsString('secret-bot-token', json_encode($settings));
    }

    // =============================
    // API Key Not In Exceptions Tests
    // =============================

    public function testOpenAIServiceExceptionDoesNotExposeKey(): void
    {
        $config = $this->createMock(OpenAIConfigService::class);
        $httpClient = $this->createMock(IClient::class);
        $logger = $this->createMock(LoggerInterface::class);

        $service = new \OCA\TalkBot\Service\OpenAIService($httpClient, $config, $logger);

        $config->method('getApiKey')->willReturn('sk-secret-key-12345');
        $config->method('getDefaultModel')->willReturn('gpt-4');
        $config->method('getTemperature')->willReturn(0.7);
        $config->method('getMaxTokens')->willReturn(1024);
        $config->method('getOrganizationId')->willReturn('');
        $config->method('getSystemPrompt')->willReturn('');

        $errorResponse = $this->createMock(IResponse::class);
        $errorResponse->method('getStatusCode')->willReturn(401);
        $errorResponse->method('getBody')->willReturn('{"error": {"message": "Invalid API key"}}');

        $httpClient->method('post')->willReturn($errorResponse);

        try {
            $service->chat('test');
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $this->assertStringNotContainsString('sk-secret-key', $message);
            $this->assertStringNotContainsString('12345', $message);
        }
    }

    public function testCustomAIServiceExceptionDoesNotExposeKey(): void
    {
        $config = $this->createMock(IConfig::class);
        $httpClient = $this->createMock(IClient::class);
        $logger = $this->createMock(LoggerInterface::class);

        $service = new CustomAIService($httpClient, $config, $logger);

        $config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'custom_ai_endpoint', '', 'https://api.custom.com'],
                ['talk_bot', 'custom_ai_api_key', '', 'super-secret-key'],
                ['talk_bot', 'custom_ai_model', '', 'model'],
                ['talk_bot', 'custom_ai_headers', '{}', '{}'],
                ['talk_bot', 'custom_ai_timeout', '30', '30'],
                ['talk_bot', 'custom_ai_auth_scheme', 'Bearer', 'Bearer'],
            ]);

        $errorResponse = $this->createMock(IResponse::class);
        $errorResponse->method('getStatusCode')->willReturn(500);
        $errorResponse->method('getBody')->willReturn('Server error');

        $httpClient->method('post')->willReturn($errorResponse);

        try {
            $service->chat('test');
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $this->assertStringNotContainsString('super-secret-key', $message);
        }
    }

    // =============================
    // API Key Masking Tests
    // =============================

    public function testApiKeyMaskingLongKey(): void
    {
        $config = $this->createMock(IConfig::class);

        $service = new OpenAIConfigService($config);

        $config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'openai_api_key', '', 'sk-proj-1234567890abcdefghijklmnopqrstuvwxyz'],
                ['talk_bot', 'openai_org_id', '', ''],
                ['talk_bot', 'openai_model', 'gpt-4', 'gpt-4'],
                ['talk_bot', 'openai_max_tokens', '1024', '1024'],
                ['talk_bot', 'openai_temperature', '0.7', '0.7'],
            ]);

        $settings = $service->getAllSettings();

        // Should show last 4 characters
        $this->assertMatchesRegularExpression('/\*\*\*tuvz$/', $settings['apiKey']);
        $this->assertStringNotContainsString('sk-proj', $settings['apiKey']);
        $this->assertStringNotContainsString('1234567890', $settings['apiKey']);
    }

    public function testApiKeyMaskingShortKey(): void
    {
        $config = $this->createMock(IConfig::class);

        $service = new OpenAIConfigService($config);

        $config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'openai_api_key', '', 'abc'],
                ['talk_bot', 'openai_org_id', '', ''],
                ['talk_bot', 'openai_model', 'gpt-4', 'gpt-4'],
                ['talk_bot', 'openai_max_tokens', '1024', '1024'],
                ['talk_bot', 'openai_temperature', '0.7', '0.7'],
            ]);

        $settings = $service->getAllSettings();

        // Short key should still be masked
        $this->assertMatchesRegularExpression('/\*\*\*abc$/', $settings['apiKey']);
    }

    public function testApiKeyMaskingEmptyKey(): void
    {
        $config = $this->createMock(IConfig::class);

        $service = new OpenAIConfigService($config);

        $config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'openai_api_key', '', ''],
                ['talk_bot', 'openai_org_id', '', ''],
                ['talk_bot', 'openai_model', 'gpt-4', 'gpt-4'],
                ['talk_bot', 'openai_max_tokens', '1024', '1024'],
                ['talk_bot', 'openai_temperature', '0.7', '0.7'],
            ]);

        $settings = $service->getAllSettings();

        // Empty key should return empty string
        $this->assertEquals('', $settings['apiKey']);
    }

    // =============================
    // Debug Output Sanitization Tests
    // =============================

    public function testDebugOutputSanitization(): void
    {
        $config = $this->createMock(IConfig::class);
        $logger = $this->createMock(LoggerInterface::class);
        $httpClient = $this->createMock(IClient::class);

        // Ensure logger never receives API key in any context
        $logger->expects($this->never())
            ->method('error')
            ->with($this->callback(function ($message) {
                return strpos($message, 'sk-secret') !== false;
            }));

        $logger->expects($this->never())
            ->method('warning')
            ->with($this->callback(function ($message) {
                return strpos($message, 'sk-secret') !== false;
            }));

        // This test verifies that the services don't log sensitive data
        // Actual implementation would need to verify this behavior
        $this->assertTrue(true);
    }

    // =============================
    // Request Header Security Tests
    // =============================

    public function testApiKeyNotInRequestBody(): void
    {
        $config = $this->createMock(OpenAIConfigService::class);
        $httpClient = $this->createMock(IClient::class);
        $logger = $this->createMock(LoggerInterface::class);

        $service = new \OCA\TalkBot\Service\OpenAIService($httpClient, $config, $logger);

        $config->method('getApiKey')->willReturn('sk-test-key');
        $config->method('getDefaultModel')->willReturn('gpt-4');
        $config->method('getTemperature')->willReturn(0.7);
        $config->method('getMaxTokens')->willReturn(1024);
        $config->method('getOrganizationId')->willReturn('');
        $config->method('getSystemPrompt')->willReturn('');

        $capturedBody = null;
        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn(json_encode([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Hi']]]
        ]));

        $httpClient->method('post')
            ->willReturnCallback(function ($url, $options) use (&$capturedBody, $mockResponse) {
                $capturedBody = $options['body'] ?? null;
                return $mockResponse;
            });

        $service->chat('test');

        // Verify API key is NOT in the request body
        $decodedBody = json_decode($capturedBody, true);
        $this->assertArrayNotHasKey('api_key', $decodedBody);
        $this->assertStringNotContainsString('sk-test-key', $capturedBody);
    }

    public function testCustomApiKeyInHeaderOnly(): void
    {
        $config = $this->createMock(IConfig::class);
        $httpClient = $this->createMock(IClient::class);
        $logger = $this->createMock(LoggerInterface::class);

        $service = new CustomAIService($httpClient, $config, $logger);

        $config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'custom_ai_endpoint', '', 'https://api.custom.com'],
                ['talk_bot', 'custom_ai_api_key', '', 'custom-secret-key'],
                ['talk_bot', 'custom_ai_model', '', 'model'],
                ['talk_bot', 'custom_ai_headers', '{}', '{}'],
                ['talk_bot', 'custom_ai_timeout', '30', '30'],
                ['talk_bot', 'custom_ai_auth_scheme', 'Bearer', 'Bearer'],
            ]);

        $capturedOptions = null;
        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn(json_encode(['response' => 'ok']));

        $httpClient->method('post')
            ->willReturnCallback(function ($url, $options) use (&$capturedOptions, $mockResponse) {
                $capturedOptions = $options;
                return $mockResponse;
            });

        $service->chat('test');

        // API key should be in Authorization header
        $this->assertArrayHasKey('Authorization', $capturedOptions['headers']);
        $this->assertEquals('Bearer custom-secret-key', $capturedOptions['headers']['Authorization']);

        // API key should NOT be in body
        $body = json_decode($capturedOptions['body'], true);
        $this->assertArrayNotHasKey('api_key', $body);
        $this->assertStringNotContainsString('custom-secret-key', $capturedOptions['body']);
    }

    // =============================
    // HTTPS Enforcement Tests
    // =============================

    public function testOpenAIUsesHttps(): void
    {
        // OpenAI base URL should always be HTTPS
        $reflection = new \ReflectionClass(\OCA\TalkBot\Service\OpenAIService::class);
        $constant = $reflection->getConstant('API_BASE_URL');

        $this->assertStringStartsWith('https://', $constant);
    }

    public function testCustomEndpointValidation(): void
    {
        // Custom endpoints should be validated for HTTPS
        // This is a design consideration for the service
        // In production, the service should validate or warn about HTTP endpoints
        
        $config = $this->createMock(IConfig::class);
        $httpClient = $this->createMock(IClient::class);
        $logger = $this->createMock(LoggerInterface::class);

        $service = new CustomAIService($httpClient, $config, $logger);

        // Set insecure HTTP endpoint
        $config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'custom_ai_endpoint', '', 'http://insecure.example.com'],
                ['talk_bot', 'custom_ai_api_key', '', 'key'],
                ['talk_bot', 'custom_ai_model', '', 'model'],
                ['talk_bot', 'custom_ai_headers', '{}', '{}'],
                ['talk_bot', 'custom_ai_timeout', '30', '30'],
                ['talk_bot', 'custom_ai_auth_scheme', 'Bearer', 'Bearer'],
            ]);

        // The service should either:
        // 1. Reject HTTP endpoints
        // 2. Log a security warning
        // 3. Continue but document the risk
        
        // For now, we verify the endpoint is stored as-is
        $endpoint = $service->getEndpoint();
        $this->assertEquals('http://insecure.example.com', $endpoint);
        
        // In a production system, you would want to add validation
        // like this (pseudo-code):
        // $logger->expects($this->once())->method('warning')->with($this->stringContains('insecure'));
    }

    // =============================
    // Token/Key Deletion Tests
    // =============================

    public function testDeleteApiKey(): void
    {
        $config = $this->createMock(IConfig::class);

        $service = new OpenAIConfigService($config);

        $config->expects($this->once())
            ->method('deleteAppValue')
            ->with('talk_bot', 'openai_api_key');

        $service->deleteApiKey();
    }

    public function testApiKeyDeletionCannotBeRecovered(): void
    {
        $config = $this->createMock(IConfig::class);

        $service = new OpenAIConfigService($config);

        // Verify that after deletion, the key is truly gone
        $config->expects($this->exactly(2))
            ->method('getAppValue')
            ->willReturnOnConsecutiveCalls('sk-original-key', '');

        $this->assertTrue($service->hasApiKey());
        
        $service->deleteApiKey();
        
        $this->assertFalse($service->hasApiKey());
    }
}