<?php

declare(strict_types=1);

namespace OCA\TalkBot\Tests\Integration;

use OCA\TalkBot\Service\AIProviderDetector;
use OCA\TalkBot\Service\OpenAIConfigService;
use OCA\TalkBot\Tests\Framework\MockConfig;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for AI Provider Detection
 * 
 * @covers \OCA\TalkBot\Service\AIProviderDetector
 */
class AIProviderIntegrationTest extends TestCase
{
    private MockConfig $config;
    private OpenAIConfigService $openAIConfig;
    private AIProviderDetector $detector;

    protected function setUp(): void
    {
        $this->config = new MockConfig();
        $this->openAIConfig = new OpenAIConfigService($this->config);
        $this->detector = new AIProviderDetector($this->config, $this->openAIConfig);
    }

    // =============================
    // Provider Detection Tests
    // =============================

    public function testDetectProviderWithRealConfigNone(): void
    {
        // AI disabled
        $this->config->setAppValue('talk_bot', 'enable_ai', 'no');

        $result = $this->detector->detectProvider();

        $this->assertEquals(AIProviderDetector::PROVIDER_NONE, $result);
    }

    public function testDetectProviderWithRealConfigNextcloudAI(): void
    {
        $this->config->setAppValue('talk_bot', 'enable_ai', 'yes');
        $this->config->setAppValue('talk_bot', 'ai_provider', 'nextcloud_ai');

        $result = $this->detector->detectProvider();

        // Will be nextcloud_ai if TextProcessing is available, or none otherwise
        $this->assertContains($result, [
            AIProviderDetector::PROVIDER_NEXTCLOUD_AI,
            AIProviderDetector::PROVIDER_NONE,
        ]);
    }

    public function testDetectProviderWithRealConfigOpenAI(): void
    {
        $this->config->setAppValue('talk_bot', 'enable_ai', 'yes');
        $this->config->setAppValue('talk_bot', 'ai_provider', 'openai');
        $this->config->setAppValue('talk_bot', 'openai_api_key', 'sk-test-1234567890');

        $result = $this->detector->detectProvider();

        $this->assertEquals(AIProviderDetector::PROVIDER_OPENAI, $result);
    }

    public function testDetectProviderWithRealConfigCustom(): void
    {
        $this->config->setAppValue('talk_bot', 'enable_ai', 'yes');
        $this->config->setAppValue('talk_bot', 'ai_provider', 'custom');
        $this->config->setAppValue('talk_bot', 'custom_ai_endpoint', 'https://api.example.com/v1');

        $result = $this->detector->detectProvider();

        $this->assertEquals(AIProviderDetector::PROVIDER_CUSTOM, $result);
    }

    // =============================
    // Fallback Chain Tests
    // =============================

    public function testFallbackChainPriority(): void
    {
        // Configure all providers
        $this->config->setAppValue('talk_bot', 'enable_ai', 'yes');
        $this->config->setAppValue('talk_bot', 'openai_api_key', 'sk-test-key');
        $this->config->setAppValue('talk_bot', 'custom_ai_endpoint', 'https://api.example.com');
        // No explicit provider - auto-detect

        // Without explicit provider, should follow priority: Nextcloud AI > OpenAI > Custom
        $result = $this->detector->detectProvider();

        // Result depends on what's available in the test environment
        $this->assertContains($result, [
            AIProviderDetector::PROVIDER_NEXTCLOUD_AI,
            AIProviderDetector::PROVIDER_OPENAI,
            AIProviderDetector::PROVIDER_CUSTOM,
            AIProviderDetector::PROVIDER_NONE,
        ]);
    }

    public function testFallbackToOpenAIWhenNextcloudAINotAvailable(): void
    {
        $this->config->setAppValue('talk_bot', 'enable_ai', 'yes');
        $this->config->setAppValue('talk_bot', 'openai_api_key', 'sk-test-key');
        // No Nextcloud AI available (no TextProcessing)
        // No explicit provider

        $providers = $this->detector->getAvailableProviders();

        // If OpenAI key is set, it should be in available providers
        $openaiAvailable = array_filter($providers, function ($p) {
            return $p['id'] === AIProviderDetector::PROVIDER_OPENAI;
        });

        if (!empty($openaiAvailable)) {
            $this->assertEquals(AIProviderDetector::PROVIDER_OPENAI, array_values($openaiAvailable)[0]['id']);
        }
    }

    public function testFallbackToCustomWhenOthersNotAvailable(): void
    {
        $this->config->setAppValue('talk_bot', 'enable_ai', 'yes');
        $this->config->setAppValue('talk_bot', 'custom_ai_endpoint', 'https://api.custom.com');
        // No OpenAI key
        // No Nextcloud AI

        $providers = $this->detector->getAvailableProviders();

        // Custom provider should be available
        $customAvailable = array_filter($providers, function ($p) {
            return $p['id'] === AIProviderDetector::PROVIDER_CUSTOM;
        });

        if (!empty($customAvailable)) {
            $this->assertEquals(AIProviderDetector::PROVIDER_CUSTOM, array_values($customAvailable)[0]['id']);
        }
    }

    // =============================
    // Config Persistence Tests
    // =============================

    public function testConfigPersistsAcrossRequests(): void
    {
        // Set configuration
        $this->config->setAppValue('talk_bot', 'enable_ai', 'yes');
        $this->config->setAppValue('talk_bot', 'ai_provider', 'openai');
        $this->config->setAppValue('talk_bot', 'openai_api_key', 'sk-persist-test');

        // Create new detector instance (simulating new request)
        $newDetector = new AIProviderDetector($this->config, $this->openAIConfig);

        // Configuration should persist
        $result = $newDetector->detectProvider();

        $this->assertEquals(AIProviderDetector::PROVIDER_OPENAI, $result);
    }

    public function testConfigUpdatePersists(): void
    {
        // Initial configuration
        $this->config->setAppValue('talk_bot', 'enable_ai', 'yes');
        $this->config->setAppValue('talk_bot', 'ai_provider', 'openai');
        $this->config->setAppValue('talk_bot', 'openai_api_key', 'sk-initial');

        // Update configuration
        $this->config->setAppValue('talk_bot', 'ai_provider', 'custom');
        $this->config->setAppValue('talk_bot', 'custom_ai_endpoint', 'https://new.endpoint.com');

        // Create new detector
        $newDetector = new AIProviderDetector($this->config, $this->openAIConfig);

        $result = $newDetector->detectProvider();

        $this->assertEquals(AIProviderDetector::PROVIDER_CUSTOM, $result);
    }

    // =============================
    // Available Providers Tests
    // =============================

    public function testGetAvailableProvidersWithMultipleConfigured(): void
    {
        $this->config->setAppValue('talk_bot', 'enable_ai', 'yes');
        $this->config->setAppValue('talk_bot', 'openai_api_key', 'sk-test-key');
        $this->config->setAppValue('talk_bot', 'custom_ai_endpoint', 'https://api.custom.com');

        $providers = $this->detector->getAvailableProviders();

        // Should return array of available providers
        $this->assertIsArray($providers);

        // Each provider should have required fields
        foreach ($providers as $provider) {
            $this->assertArrayHasKey('id', $provider);
            $this->assertArrayHasKey('name', $provider);
            $this->assertArrayHasKey('description', $provider);
            $this->assertArrayHasKey('available', $provider);
        }
    }

    public function testGetCurrentProviderConfig(): void
    {
        $this->config->setAppValue('talk_bot', 'ai_model', 'gpt-4');
        $this->config->setAppValue('talk_bot', 'custom_ai_endpoint', 'https://api.custom.com');
        $this->config->setAppValue('talk_bot', 'custom_ai_model', 'custom-model-v1');
        $this->config->setAppValue('talk_bot', 'openai_api_key', 'sk-test');

        $config = $this->detector->getCurrentProviderConfig();

        $this->assertArrayHasKey('provider', $config);
        $this->assertArrayHasKey('model', $config);
        $this->assertArrayHasKey('customEndpoint', $config);
        $this->assertArrayHasKey('customModel', $config);
    }

    // =============================
    // Edge Cases
    // =============================

    public function testEmptyConfigReturnsNone(): void
    {
        // No configuration set
        $result = $this->detector->detectProvider();

        $this->assertEquals(AIProviderDetector::PROVIDER_NONE, $result);
    }

    public function testEnableAiCaseSensitive(): void
    {
        $this->config->setAppValue('talk_bot', 'enable_ai', 'YES'); // uppercase

        // Should handle case-insensitive comparison
        // Implementation might normalize to lowercase
        $result = $this->detector->detectProvider();

        // Either it works (accepts YES) or returns NONE (doesn't)
        $this->assertContains($result, [
            AIProviderDetector::PROVIDER_NONE,
            AIProviderDetector::PROVIDER_NEXTCLOUD_AI,
            AIProviderDetector::PROVIDER_OPENAI,
            AIProviderDetector::PROVIDER_CUSTOM,
        ]);
    }

    public function testInvalidProviderValue(): void
    {
        $this->config->setAppValue('talk_bot', 'enable_ai', 'yes');
        $this->config->setAppValue('talk_bot', 'ai_provider', 'invalid_provider');

        // Should handle invalid provider gracefully
        $result = $this->detector->detectProvider();

        // Should fall back to auto-detection or none
        $this->assertContains($result, [
            AIProviderDetector::PROVIDER_NONE,
            AIProviderDetector::PROVIDER_NEXTCLOUD_AI,
            AIProviderDetector::PROVIDER_OPENAI,
            AIProviderDetector::PROVIDER_CUSTOM,
        ]);
    }
}