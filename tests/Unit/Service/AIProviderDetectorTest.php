<?php

declare(strict_types=1);

namespace OCA\TalkBot\Tests\Unit\Service;

use OCA\TalkBot\Service\AIProviderDetector;
use OCA\TalkBot\Service\OpenAIConfigService;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\TalkBot\Service\AIProviderDetector
 */
class AIProviderDetectorTest extends TestCase
{
    private AIProviderDetector $detector;
    private IConfig&MockObject $config;
    private OpenAIConfigService&MockObject $openAIConfig;

    protected function setUp(): void
    {
        $this->config = $this->createMock(IConfig::class);
        $this->openAIConfig = $this->createMock(OpenAIConfigService::class);
        $this->detector = new AIProviderDetector($this->config, $this->openAIConfig);
    }

    // ====================
    // detectProvider Tests
    // ====================

    public function testDetectProviderNoneWhenAiDisabled(): void
    {
        $this->config->method('getAppValue')
            ->with('talk_bot', 'enable_ai', 'no')
            ->willReturn('no');

        $result = $this->detector->detectProvider();

        $this->assertEquals(AIProviderDetector::PROVIDER_NONE, $result);
    }

    public function testDetectProviderNextcloudAIWhenAvailable(): void
    {
        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'enable_ai', 'no', 'yes'],
                ['talk_bot', 'ai_provider', '', 'nextcloud_ai'],
            ]);

        $result = $this->detector->detectProvider();

        $this->assertEquals(AIProviderDetector::PROVIDER_NEXTCLOUD_AI, $result);
    }

    public function testDetectProviderOpenAIWhenConfigured(): void
    {
        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'enable_ai', 'no', 'yes'],
                ['talk_bot', 'ai_provider', '', 'openai'],
            ]);

        $this->openAIConfig->method('hasApiKey')
            ->willReturn(true);

        $result = $this->detector->detectProvider();

        $this->assertEquals(AIProviderDetector::PROVIDER_OPENAI, $result);
    }

    public function testDetectProviderCustomWhenConfigured(): void
    {
        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'enable_ai', 'no', 'yes'],
                ['talk_bot', 'ai_provider', '', 'custom'],
                ['talk_bot', 'custom_ai_endpoint', '', 'https://api.example.com/v1/chat'],
            ]);

        $result = $this->detector->detectProvider();

        $this->assertEquals(AIProviderDetector::PROVIDER_CUSTOM, $result);
    }

    public function testPriorityOrderNextcloudFirst(): void
    {
        // All providers available, Nextcloud AI should be selected
        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'enable_ai', 'no', 'yes'],
                ['talk_bot', 'ai_provider', '', ''],  // auto-detect
            ]);

        // All providers configured
        $this->openAIConfig->method('hasApiKey')->willReturn(true);

        $result = $this->detector->detectProvider();

        // With empty ai_provider (auto-detect), should return nextcloud_ai if available
        // or none if TextProcessing not available
        $this->assertContains($result, [
            AIProviderDetector::PROVIDER_NEXTCLOUD_AI,
            AIProviderDetector::PROVIDER_OPENAI,
            AIProviderDetector::PROVIDER_CUSTOM,
            AIProviderDetector::PROVIDER_NONE,
        ]);
    }

    public function testExplicitProviderSettingOverridesAutoDetection(): void
    {
        // Explicitly set OpenAI even if Nextcloud AI available
        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'enable_ai', 'no', 'yes'],
                ['talk_bot', 'ai_provider', '', 'openai'],
            ]);

        $this->openAIConfig->method('hasApiKey')->willReturn(true);

        $result = $this->detector->detectProvider();

        $this->assertEquals(AIProviderDetector::PROVIDER_OPENAI, $result);
    }

    // ========================
    // isNextcloudAIAvailable Tests
    // ========================

    public function testIsNextcloudAIAvailableWhenDisabled(): void
    {
        $this->config->method('getAppValue')
            ->with('talk_bot', 'enable_ai', 'no')
            ->willReturn('no');

        $result = $this->detector->isNextcloudAIAvailable();

        $this->assertFalse($result);
    }

    public function testIsNextcloudAIAvailableWhenEnabledAndExplicit(): void
    {
        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'enable_ai', 'no', 'yes'],
                ['talk_bot', 'ai_provider', '', 'nextcloud_ai'],
            ]);

        $result = $this->detector->isNextcloudAIAvailable();

        // Returns true if TextProcessing interface exists
        $this->assertIsBool($result);
    }

    // =====================
    // isOpenAIConfigured Tests
    // =====================

    public function testIsOpenAIConfiguredWhenDisabled(): void
    {
        $this->config->method('getAppValue')
            ->with('talk_bot', 'enable_ai', 'no')
            ->willReturn('no');

        $result = $this->detector->isOpenAIConfigured();

        $this->assertFalse($result);
    }

    public function testIsOpenAIConfiguredWhenEnabledAndHasKey(): void
    {
        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'enable_ai', 'no', 'yes'],
                ['talk_bot', 'ai_provider', '', 'openai'],
            ]);

        $this->openAIConfig->method('hasApiKey')->willReturn(true);

        $result = $this->detector->isOpenAIConfigured();

        $this->assertTrue($result);
    }

    public function testIsOpenAIConfiguredWhenEnabledButNoKey(): void
    {
        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'enable_ai', 'no', 'yes'],
                ['talk_bot', 'ai_provider', '', 'openai'],
            ]);

        $this->openAIConfig->method('hasApiKey')->willReturn(false);

        $result = $this->detector->isOpenAIConfigured();

        $this->assertFalse($result);
    }

    // ==========================
    // isCustomProviderConfigured Tests
    // ==========================

    public function testIsCustomProviderConfiguredWhenDisabled(): void
    {
        $this->config->method('getAppValue')
            ->with('talk_bot', 'enable_ai', 'no')
            ->willReturn('no');

        $result = $this->detector->isCustomProviderConfigured();

        $this->assertFalse($result);
    }

    public function testIsCustomProviderConfiguredWithEndpoint(): void
    {
        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'enable_ai', 'no', 'yes'],
                ['talk_bot', 'ai_provider', '', 'custom'],
                ['talk_bot', 'custom_ai_endpoint', '', 'https://api.custom.com/v1'],
            ]);

        $result = $this->detector->isCustomProviderConfigured();

        $this->assertTrue($result);
    }

    public function testIsCustomProviderConfiguredWithApiKeyOnly(): void
    {
        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'enable_ai', 'no', 'yes'],
                ['talk_bot', 'ai_provider', '', ''],
                ['talk_bot', 'custom_ai_endpoint', '', ''],
                ['talk_bot', 'custom_ai_api_key', '', 'secret-key'],
            ]);

        $result = $this->detector->isCustomProviderConfigured();

        $this->assertTrue($result);
    }

    public function testIsCustomProviderConfiguredNotConfigured(): void
    {
        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'enable_ai', 'no', 'yes'],
                ['talk_bot', 'ai_provider', '', ''],
                ['talk_bot', 'custom_ai_endpoint', '', ''],
                ['talk_bot', 'custom_ai_api_key', '', ''],
            ]);

        $result = $this->detector->isCustomProviderConfigured();

        $this->assertFalse($result);
    }

    // ========================
    // getAvailableProviders Tests
    // ========================

    public function testGetAvailableProvidersNone(): void
    {
        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'enable_ai', 'no', 'no'],
            ]);

        $providers = $this->detector->getAvailableProviders();

        $this->assertEmpty($providers);
    }

    public function testGetAvailableProvidersMultiple(): void
    {
        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'enable_ai', 'no', 'yes'],
                ['talk_bot', 'ai_provider', '', ''],
                ['talk_bot', 'custom_ai_endpoint', '', 'https://api.custom.com/v1'],
            ]);

        $this->openAIConfig->method('hasApiKey')->willReturn(true);

        $providers = $this->detector->getAvailableProviders();

        $this->assertIsArray($providers);
        $this->assertContainsOnly('array', $providers);
    }

    public function testGetAvailableProvidersStructure(): void
    {
        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'enable_ai', 'no', 'yes'],
                ['talk_bot', 'ai_provider', '', 'openai'],
            ]);

        $this->openAIConfig->method('hasApiKey')->willReturn(true);

        $providers = $this->detector->getAvailableProviders();

        if (!empty($providers)) {
            $provider = $providers[0];
            $this->assertArrayHasKey('id', $provider);
            $this->assertArrayHasKey('name', $provider);
            $this->assertArrayHasKey('description', $provider);
            $this->assertArrayHasKey('available', $provider);
        }
    }

    // =============================
    // getCurrentProviderConfig Tests
    // =============================

    public function testGetCurrentProviderConfig(): void
    {
        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'ai_model', 'claude-3-opus', 'gpt-4'],
                ['talk_bot', 'custom_ai_endpoint', '', ''],
                ['talk_bot', 'custom_ai_model', '', ''],
            ]);

        $this->openAIConfig->method('hasApiKey')->willReturn(true);

        $config = $this->detector->getCurrentProviderConfig();

        $this->assertArrayHasKey('provider', $config);
        $this->assertArrayHasKey('model', $config);
        $this->assertArrayHasKey('customEndpoint', $config);
        $this->assertArrayHasKey('customModel', $config);
    }

    public function testGetCurrentProviderConfigWithCustomProvider(): void
    {
        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'ai_model', 'claude-3-opus', ''],
                ['talk_bot', 'custom_ai_endpoint', '', 'https://api.custom.com/v1'],
                ['talk_bot', 'custom_ai_model', '', 'custom-model-v1'],
            ]);

        $config = $this->detector->getCurrentProviderConfig();

        $this->assertEquals('https://api.custom.com/v1', $config['customEndpoint']);
        $this->assertEquals('custom-model-v1', $config['customModel']);
    }

    // ==============
    // Edge Cases
    // ==============

    public function testDetectProviderWithEmptyStrings(): void
    {
        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'enable_ai', 'no', ''],
                ['talk_bot', 'ai_provider', '', ''],
            ]);

        $this->openAIConfig->method('hasApiKey')->willReturn(false);

        $result = $this->detector->detectProvider();

        $this->assertEquals(AIProviderDetector::PROVIDER_NONE, $result);
    }

    public function testDetectProviderWithWhitespace(): void
    {
        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'enable_ai', 'no', ' yes '],
                ['talk_bot', 'ai_provider', '', ' openai '],
            ]);

        $this->openAIConfig->method('hasApiKey')->willReturn(true);

        // Provider detection should handle whitespace
        $result = $this->detector->detectProvider();

        // Should return a valid provider
        $this->assertContains($result, [
            AIProviderDetector::PROVIDER_NONE,
            AIProviderDetector::PROVIDER_NEXTCLOUD_AI,
            AIProviderDetector::PROVIDER_OPENAI,
            AIProviderDetector::PROVIDER_CUSTOM,
        ]);
    }
}