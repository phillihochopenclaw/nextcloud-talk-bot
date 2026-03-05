<?php

declare(strict_types=1);

namespace OCA\TalkBot\Tests\Unit\Service;

use OCA\TalkBot\Service\OpenAIConfigService;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\TalkBot\Service\OpenAIConfigService
 */
class OpenAIConfigServiceTest extends TestCase
{
    private OpenAIConfigService $service;
    private IConfig&MockObject $config;

    protected function setUp(): void
    {
        $this->config = $this->createMock(IConfig::class);
        $this->service = new OpenAIConfigService($this->config);
    }

    // ==================
    // API Key Tests
    // ==================

    public function testGetApiKey(): void
    {
        $this->config->method('getAppValue')
            ->with('talk_bot', 'openai_api_key', '')
            ->willReturn('sk-test-1234567890');

        $apiKey = $this->service->getApiKey();

        $this->assertEquals('sk-test-1234567890', $apiKey);
    }

    public function testGetApiKeyEmpty(): void
    {
        $this->config->method('getAppValue')
            ->with('talk_bot', 'openai_api_key', '')
            ->willReturn('');

        $apiKey = $this->service->getApiKey();

        $this->assertEquals('', $apiKey);
    }

    public function testSetApiKey(): void
    {
        $this->config->expects($this->once())
            ->method('setAppValue')
            ->with('talk_bot', 'openai_api_key', 'sk-new-key');

        $this->service->setApiKey('sk-new-key');
    }

    public function testHasApiKeyTrue(): void
    {
        $this->config->method('getAppValue')
            ->with('talk_bot', 'openai_api_key', '')
            ->willReturn('sk-existing-key');

        $result = $this->service->hasApiKey();

        $this->assertTrue($result);
    }

    public function testHasApiKeyFalse(): void
    {
        $this->config->method('getAppValue')
            ->with('talk_bot', 'openai_api_key', '')
            ->willReturn('');

        $result = $this->service->hasApiKey();

        $this->assertFalse($result);
    }

    public function testHasApiKeyFalseWhenWhitespace(): void
    {
        $this->config->method('getAppValue')
            ->with('talk_bot', 'openai_api_key', '')
            ->willReturn('   ');

        $result = $this->service->hasApiKey();

        $this->assertFalse($result);
    }

    public function testDeleteApiKey(): void
    {
        $this->config->expects($this->once())
            ->method('deleteAppValue')
            ->with('talk_bot', 'openai_api_key');

        $this->service->deleteApiKey();
    }

    // ==========================
    // Organization ID Tests
    // ==========================

    public function testGetOrganizationId(): void
    {
        $this->config->method('getAppValue')
            ->with('talk_bot', 'openai_org_id', '')
            ->willReturn('org-123456');

        $orgId = $this->service->getOrganizationId();

        $this->assertEquals('org-123456', $orgId);
    }

    public function testGetOrganizationIdEmpty(): void
    {
        $this->config->method('getAppValue')
            ->with('talk_bot', 'openai_org_id', '')
            ->willReturn('');

        $orgId = $this->service->getOrganizationId();

        $this->assertEquals('', $orgId);
    }

    public function testSetOrganizationId(): void
    {
        $this->config->expects($this->once())
            ->method('setAppValue')
            ->with('talk_bot', 'openai_org_id', 'org-new');

        $this->service->setOrganizationId('org-new');
    }

    // ==================
    // Model Tests
    // ==================

    public function testGetDefaultModel(): void
    {
        $this->config->method('getAppValue')
            ->with('talk_bot', 'openai_model', 'gpt-4')
            ->willReturn('gpt-4-turbo');

        $model = $this->service->getDefaultModel();

        $this->assertEquals('gpt-4-turbo', $model);
    }

    public function testGetDefaultModelDefaultValue(): void
    {
        $this->config->method('getAppValue')
            ->with('talk_bot', 'openai_model', 'gpt-4')
            ->willReturn('gpt-4');

        $model = $this->service->getDefaultModel();

        $this->assertEquals('gpt-4', $model);
    }

    public function testSetDefaultModel(): void
    {
        $this->config->expects($this->once())
            ->method('setAppValue')
            ->with('talk_bot', 'openai_model', 'gpt-4-turbo');

        $this->service->setDefaultModel('gpt-4-turbo');
    }

    // ======================
    // Max Tokens Tests
    // ======================

    public function testGetMaxTokens(): void
    {
        $this->config->method('getAppValue')
            ->with('talk_bot', 'openai_max_tokens', '1024')
            ->willReturn('2048');

        $maxTokens = $this->service->getMaxTokens();

        $this->assertEquals(2048, $maxTokens);
    }

    public function testGetMaxTokensDefault(): void
    {
        $this->config->method('getAppValue')
            ->with('talk_bot', 'openai_max_tokens', '1024')
            ->willReturn('1024');

        $maxTokens = $this->service->getMaxTokens();

        $this->assertEquals(1024, $maxTokens);
    }

    public function testSetMaxTokens(): void
    {
        $this->config->expects($this->once())
            ->method('setAppValue')
            ->with('talk_bot', 'openai_max_tokens', '4096');

        $this->service->setMaxTokens(4096);
    }

    // ======================
    // Temperature Tests
    // ======================

    public function testGetTemperature(): void
    {
        $this->config->method('getAppValue')
            ->with('talk_bot', 'openai_temperature', '0.7')
            ->willReturn('0.5');

        $temperature = $this->service->getTemperature();

        $this->assertEquals(0.5, $temperature);
    }

    public function testGetTemperatureDefault(): void
    {
        $this->config->method('getAppValue')
            ->with('talk_bot', 'openai_temperature', '0.7')
            ->willReturn('0.7');

        $temperature = $this->service->getTemperature();

        $this->assertEquals(0.7, $temperature);
    }

    public function testSetTemperature(): void
    {
        $this->config->expects($this->once())
            ->method('setAppValue')
            ->with('talk_bot', 'openai_temperature', '0.9');

        $this->service->setTemperature(0.9);
    }

    // ========================
    // getAllSettings Tests
    // ========================

    public function testGetAllSettings(): void
    {
        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'openai_api_key', '', 'sk-test-12345678'],
                ['talk_bot', 'openai_org_id', '', 'org-123'],
                ['talk_bot', 'openai_model', 'gpt-4', 'gpt-4-turbo'],
                ['talk_bot', 'openai_max_tokens', '1024', '2048'],
                ['talk_bot', 'openai_temperature', '0.7', '0.8'],
            ]);

        $settings = $this->service->getAllSettings();

        $this->assertIsArray($settings);
        $this->assertArrayHasKey('apiKey', $settings);
        $this->assertArrayHasKey('organizationId', $settings);
        $this->assertArrayHasKey('model', $settings);
        $this->assertArrayHasKey('maxTokens', $settings);
        $this->assertArrayHasKey('temperature', $settings);
    }

    public function testApiKeyMaskedInGetAllSettings(): void
    {
        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'openai_api_key', '', 'sk-test-1234567890abcdef'],
                ['talk_bot', 'openai_org_id', '', ''],
                ['talk_bot', 'openai_model', 'gpt-4', 'gpt-4'],
                ['talk_bot', 'openai_max_tokens', '1024', '1024'],
                ['talk_bot', 'openai_temperature', '0.7', '0.7'],
            ]);

        $settings = $this->service->getAllSettings();

        // API key should be masked (only last 4 characters visible)
        $this->assertStringContainsString('***', $settings['apiKey']);
        $this->assertStringEndsWith('cdef', $settings['apiKey']);
        $this->assertStringNotContainsString('sk-test-1234567890', $settings['apiKey']);
    }

    public function testApiKeyNotShownWhenEmpty(): void
    {
        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'openai_api_key', '', ''],
                ['talk_bot', 'openai_org_id', '', ''],
                ['talk_bot', 'openai_model', 'gpt-4', 'gpt-4'],
                ['talk_bot', 'openai_max_tokens', '1024', '1024'],
                ['talk_bot', 'openai_temperature', '0.7', '0.7'],
            ]);

        $settings = $this->service->getAllSettings();

        $this->assertEquals('', $settings['apiKey']);
    }

    public function testGetAllSettingsShortApiKey(): void
    {
        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'openai_api_key', '', 'abc'],
                ['talk_bot', 'openai_org_id', '', ''],
                ['talk_bot', 'openai_model', 'gpt-4', 'gpt-4'],
                ['talk_bot', 'openai_max_tokens', '1024', '1024'],
                ['talk_bot', 'openai_temperature', '0.7', '0.7'],
            ]);

        $settings = $this->service->getAllSettings();

        // Short key should still be handled
        $this->assertIsString($settings['apiKey']);
    }

    // ===============
    // Edge Cases
    // ===============

    public function testMaxTokensHandlesInvalidValue(): void
    {
        $this->config->method('getAppValue')
            ->with('talk_bot', 'openai_max_tokens', '1024')
            ->willReturn('invalid');

        $maxTokens = $this->service->getMaxTokens();

        // Should return 0 for invalid value (cast to int)
        $this->assertEquals(0, $maxTokens);
    }

    public function testTemperatureHandlesInvalidValue(): void
    {
        $this->config->method('getAppValue')
            ->with('talk_bot', 'openai_temperature', '0.7')
            ->willReturn('invalid');

        $temperature = $this->service->getTemperature();

        // Should return 0 for invalid value (cast to float)
        $this->assertEquals(0.0, $temperature);
    }
}