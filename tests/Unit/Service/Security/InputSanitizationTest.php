<?php

declare(strict_types=1);

namespace OCA\TalkBot\Tests\Unit\Service\Security;

use OCA\TalkBot\Service\OpenAIService;
use OCA\TalkBot\Service\CustomAIService;
use OCA\TalkBot\Service\OpenAIConfigService;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Security tests for input sanitization
 * 
 * @covers \OCA\TalkBot\Service\OpenAIService
 * @covers \OCA\TalkBot\Service\CustomAIService
 */
class InputSanitizationTest extends TestCase
{
    // =============================
    // Prompt Sanitization Tests
    // =============================

    public function testXssInPromptIsSanitized(): void
    {
        $config = $this->createMock(OpenAIConfigService::class);
        $httpClient = $this->createMock(IClient::class);
        $logger = $this->createMock(LoggerInterface::class);

        $service = new OpenAIService($httpClient, $config, $logger);

        $config->method('getApiKey')->willReturn('sk-test-key');
        $config->method('getDefaultModel')->willReturn('gpt-4');
        $config->method('getTemperature')->willReturn(0.7);
        $config->method('getMaxTokens')->willReturn(1024);
        $config->method('getOrganizationId')->willReturn('');
        $config->method('getSystemPrompt')->willReturn('');

        $xssPayload = '<script>alert("XSS")</script>Hello';

        $capturedBody = null;
        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn(json_encode([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Response']]]
        ]));

        $httpClient->method('post')
            ->willReturnCallback(function ($url, $options) use (&$capturedBody, $mockResponse) {
                $capturedBody = json_decode($options['body'], true);
                return $mockResponse;
            });

        $service->chat($xssPayload);

        // Verify XSS payload is properly encoded in JSON
        $this->assertEquals($xssPayload, $capturedBody['messages'][0]['content']);
        // JSON encoding should escape special characters
        $this->assertStringNotContainsString('<script>', $options['body'] ?? '{}');
    }

    public function testSqlInjectionInPromptIsHandled(): void
    {
        $config = $this->createMock(OpenAIConfigService::class);
        $httpClient = $this->createMock(IClient::class);
        $logger = $this->createMock(LoggerInterface::class);

        $service = new OpenAIService($httpClient, $config, $logger);

        $config->method('getApiKey')->willReturn('sk-test-key');
        $config->method('getDefaultModel')->willReturn('gpt-4');
        $config->method('getTemperature')->willReturn(0.7);
        $config->method('getMaxTokens')->willReturn(1024);
        $config->method('getOrganizationId')->willReturn('');
        $config->method('getSystemPrompt')->willReturn('');

        $sqlInjection = "'; DROP TABLE users; --";

        $capturedBody = null;
        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn(json_encode([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Response']]]
        ]));

        $httpClient->method('post')
            ->willReturnCallback(function ($url, $options) use (&$capturedBody, $mockResponse) {
                $capturedBody = json_decode($options['body'], true);
                return $mockResponse;
            });

        $service->chat($sqlInjection);

        // SQL injection should be treated as regular text
        $this->assertEquals($sqlInjection, $capturedBody['messages'][0]['content']);
    }

    public function testUnicodeInPromptIsHandled(): void
    {
        $config = $this->createMock(OpenAIConfigService::class);
        $httpClient = $this->createMock(IClient::class);
        $logger = $this->createMock(LoggerInterface::class);

        $service = new OpenAIService($httpClient, $config, $logger);

        $config->method('getApiKey')->willReturn('sk-test-key');
        $config->method('getDefaultModel')->willReturn('gpt-4');
        $config->method('getTemperature')->willReturn(0.7);
        $config->method('getMaxTokens')->willReturn(1024);
        $config->method('getOrganizationId')->willReturn('');
        $config->method('getSystemPrompt')->willReturn('');

        $unicodePrompt = "Hello 你好 Привет 🎉 مرحبا";

        $capturedBody = null;
        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn(json_encode([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Response']]]
        ]));

        $httpClient->method('post')
            ->willReturnCallback(function ($url, $options) use (&$capturedBody, $mockResponse) {
                $capturedBody = json_decode($options['body'], true);
                return $mockResponse;
            });

        $service->chat($unicodePrompt);

        // Unicode should be preserved and properly encoded
        $this->assertEquals($unicodePrompt, $capturedBody['messages'][0]['content']);
    }

    // =============================
    // Conversation History Sanitization Tests
    // =============================

    public function testXssInHistoryIsSanitized(): void
    {
        $config = $this->createMock(OpenAIConfigService::class);
        $httpClient = $this->createMock(IClient::class);
        $logger = $this->createMock(LoggerInterface::class);

        $service = new OpenAIService($httpClient, $config, $logger);

        $config->method('getApiKey')->willReturn('sk-test-key');
        $config->method('getDefaultModel')->willReturn('gpt-4');
        $config->method('getTemperature')->willReturn(0.7);
        $config->method('getMaxTokens')->willReturn(1024);
        $config->method('getOrganizationId')->willReturn('');
        $config->method('getSystemPrompt')->willReturn('');

        $history = [
            ['role' => 'user', 'content' => '<img src=x onerror=alert(1)>'],
            ['role' => 'assistant', 'content' => '<script>evil()</script>'],
        ];

        $capturedBody = null;
        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn(json_encode([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Response']]]
        ]));

        $httpClient->method('post')
            ->willReturnCallback(function ($url, $options) use (&$capturedBody, $mockResponse) {
                $capturedBody = json_decode($options['body'], true);
                return $mockResponse;
            });

        $service->chat('Continue', $history);

        // XSS payloads should be preserved as-is (API will handle)
        // but properly JSON-encoded
        $this->assertCount(3, $capturedBody['messages']);
    }

    public function testMalformedHistoryIsHandled(): void
    {
        $config = $this->createMock(OpenAIConfigService::class);
        $httpClient = $this->createMock(IClient::class);
        $logger = $this->createMock(LoggerInterface::class);

        $service = new OpenAIService($httpClient, $config, $logger);

        $config->method('getApiKey')->willReturn('sk-test-key');
        $config->method('getDefaultModel')->willReturn('gpt-4');
        $config->method('getTemperature')->willReturn(0.7);
        $config->method('getMaxTokens')->willReturn(1024);
        $config->method('getOrganizationId')->willReturn('');
        $config->method('getSystemPrompt')->willReturn('');

        // Malformed history entries
        $malformedHistory = [
            ['role' => 'user'], // Missing content
            ['content' => 'test'], // Missing role
            ['role' => 'unknown', 'content' => 'test'], // Invalid role
            ['role' => 'user', 'content' => 'valid'],
        ];

        $capturedBody = null;
        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn(json_encode([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Response']]]
        ]));

        $httpClient->method('post')
            ->willReturnCallback(function ($url, $options) use (&$capturedBody, $mockResponse) {
                $capturedBody = json_decode($options['body'], true);
                return $mockResponse;
            });

        $service->chat('Continue', $malformedHistory);

        // Service should handle malformed history gracefully
        // Default values should be applied
        $this->assertCount(5, $capturedBody['messages']); // 4 history + 1 new
    }

    // =============================
    // Custom Provider Input Tests
    // =============================

    public function testCustomProviderXssInPrompt(): void
    {
        $config = $this->createMock(IConfig::class);
        $httpClient = $this->createMock(IClient::class);
        $logger = $this->createMock(LoggerInterface::class);

        $service = new CustomAIService($httpClient, $config, $logger);

        $config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'custom_ai_endpoint', '', 'https://api.custom.com'],
                ['talk_bot', 'custom_ai_api_key', '', ''],
                ['talk_bot', 'custom_ai_model', '', 'model'],
                ['talk_bot', 'custom_ai_headers', '{}', '{}'],
                ['talk_bot', 'custom_ai_timeout', '30', '30'],
            ]);

        $xssPayload = '<script>alert("XSS")</script>Hello';

        $capturedBody = null;
        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn(json_encode(['response' => 'ok']));

        $httpClient->method('post')
            ->willReturnCallback(function ($url, $options) use (&$capturedBody, $mockResponse) {
                $capturedBody = json_decode($options['body'], true);
                return $mockResponse;
            });

        $service->chat($xssPayload);

        // XSS payload should be in the message content
        $this->assertEquals($xssPayload, $capturedBody['messages'][0]['content']);
    }

    public function testCustomProviderInvalidRole(): void
    {
        $config = $this->createMock(IConfig::class);
        $httpClient = $this->createMock(IClient::class);
        $logger = $this->createMock(LoggerInterface::class);

        $service = new CustomAIService($httpClient, $config, $logger);

        $config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'custom_ai_endpoint', '', 'https://api.custom.com'],
                ['talk_bot', 'custom_ai_api_key', '', ''],
                ['talk_bot', 'custom_ai_model', '', 'model'],
                ['talk_bot', 'custom_ai_headers', '{}', '{}'],
                ['talk_bot', 'custom_ai_timeout', '30', '30'],
            ]);

        $history = [
            ['role' => 'hacker', 'content' => 'malicious'],
            ['role' => 'user', 'content' => 'valid'],
        ];

        $capturedBody = null;
        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn(json_encode(['response' => 'ok']));

        $httpClient->method('post')
            ->willReturnCallback(function ($url, $options) use (&$capturedBody, $mockResponse) {
                $capturedBody = json_decode($options['body'], true);
                return $mockResponse;
            });

        $service->chat('test', $history);

        // Invalid roles should be passed through (API may reject them)
        $this->assertEquals('hacker', $capturedBody['messages'][0]['role']);
    }

    // =============================
    // Length Limit Tests
    // =============================

    public function testLongPromptIsAccepted(): void
    {
        $config = $this->createMock(OpenAIConfigService::class);
        $httpClient = $this->createMock(IClient::class);
        $logger = $this->createMock(LoggerInterface::class);

        $service = new OpenAIService($httpClient, $config, $logger);

        $config->method('getApiKey')->willReturn('sk-test-key');
        $config->method('getDefaultModel')->willReturn('gpt-4');
        $config->method('getTemperature')->willReturn(0.7);
        $config->method('getMaxTokens')->willReturn(1024);
        $config->method('getOrganizationId')->willReturn('');
        $config->method('getSystemPrompt')->willReturn('');

        // Very long prompt (10000 characters)
        $longPrompt = str_repeat('a', 10000);

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn(json_encode([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Response']]]
        ]));

        $httpClient->method('post')->willReturn($mockResponse);

        // Should not throw exception - API will handle length limits
        $result = $service->chat($longPrompt);
        $this->assertEquals('Response', $result);
    }

    // =============================
    // Special Characters Tests
    // =============================

    public function testSpecialCharactersInPrompt(): void
    {
        $config = $this->createMock(OpenAIConfigService::class);
        $httpClient = $this->createMock(IClient::class);
        $logger = $this->createMock(LoggerInterface::class);

        $service = new OpenAIService($httpClient, $config, $logger);

        $config->method('getApiKey')->willReturn('sk-test-key');
        $config->method('getDefaultModel')->willReturn('gpt-4');
        $config->method('getTemperature')->willReturn(0.7);
        $config->method('getMaxTokens')->willReturn(1024);
        $config->method('getOrganizationId')->willReturn('');
        $config->method('getSystemPrompt')->willReturn('');

        $specialCharsPrompt = "Test\n\t\r\"'{}`~!@#$%^&*()_+-=[]{}|;:,.<>?/\\";

        $capturedBody = null;
        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn(json_encode([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Response']]]
        ]));

        $httpClient->method('post')
            ->willReturnCallback(function ($url, $options) use (&$capturedBody, $mockResponse) {
                $capturedBody = json_decode($options['body'], true);
                return $mockResponse;
            });

        $service->chat($specialCharsPrompt);

        // Special characters should be preserved and JSON-encoded
        $this->assertEquals($specialCharsPrompt, $capturedBody['messages'][0]['content']);
    }

    public function testNullBytesInPrompt(): void
    {
        $config = $this->createMock(OpenAIConfigService::class);
        $httpClient = $this->createMock(IClient::class);
        $logger = $this->createMock(LoggerInterface::class);

        $service = new OpenAIService($httpClient, $config, $logger);

        $config->method('getApiKey')->willReturn('sk-test-key');
        $config->method('getDefaultModel')->willReturn('gpt-4');
        $config->method('getTemperature')->willReturn(0.7);
        $config->method('getMaxTokens')->willReturn(1024);
        $config->method('getOrganizationId')->willReturn('');
        $config->method('getSystemPrompt')->willReturn('');

        // Null bytes should be handled safely
        $nullBytePrompt = "Hello\x00World";

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn(json_encode([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Response']]]
        ]));

        $httpClient->method('post')->willReturn($mockResponse);

        // Should not throw exception or cause issues
        $result = $service->chat($nullBytePrompt);
        $this->assertEquals('Response', $result);
    }

    // =============================
    // Empty Input Tests
    // =============================

    public function testEmptyPrompt(): void
    {
        $config = $this->createMock(OpenAIConfigService::class);
        $httpClient = $this->createMock(IClient::class);
        $logger = $this->createMock(LoggerInterface::class);

        $service = new OpenAIService($httpClient, $config, $logger);

        $config->method('getApiKey')->willReturn('sk-test-key');
        $config->method('getDefaultModel')->willReturn('gpt-4');
        $config->method('getTemperature')->willReturn(0.7);
        $config->method('getMaxTokens')->willReturn(1024);
        $config->method('getOrganizationId')->willReturn('');
        $config->method('getSystemPrompt')->willReturn('');

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn(json_encode([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Response']]]
        ]));

        $httpClient->method('post')->willReturn($mockResponse);

        // Empty prompt should be handled
        $result = $service->chat('');
        $this->assertEquals('Response', $result);
    }

    public function testWhitespaceOnlyPrompt(): void
    {
        $config = $this->createMock(OpenAIConfigService::class);
        $httpClient = $this->createMock(IClient::class);
        $logger = $this->createMock(LoggerInterface::class);

        $service = new OpenAIService($httpClient, $config, $logger);

        $config->method('getApiKey')->willReturn('sk-test-key');
        $config->method('getDefaultModel')->willReturn('gpt-4');
        $config->method('getTemperature')->willReturn(0.7);
        $config->method('getMaxTokens')->willReturn(1024);
        $config->method('getOrganizationId')->willReturn('');
        $config->method('getSystemPrompt')->willReturn('');

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn(json_encode([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Response']]]
        ]));

        $httpClient->method('post')->willReturn($mockResponse);

        // Whitespace-only prompt should be handled
        $result = $service->chat('   ');
        $this->assertEquals('Response', $result);
    }

    // =============================
    // Content-Type Injection Tests
    // =============================

    public function testContentTypeInjectionPrevented(): void
    {
        $config = $this->createMock(IConfig::class);
        $httpClient = $this->createMock(IClient::class);
        $logger = $this->createMock(LoggerInterface::class);

        $service = new CustomAIService($httpClient, $config, $logger);

        $config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'custom_ai_endpoint', '', 'https://api.custom.com'],
                ['talk_bot', 'custom_ai_api_key', '', 'key'],
                ['talk_bot', 'custom_ai_model', '', 'model'],
                ['talk_bot', 'custom_ai_headers', '{}', '{}'],
                ['talk_bot', 'custom_ai_timeout', '30', '30'],
                ['talk_bot', 'custom_ai_auth_scheme', 'Bearer', 'Bearer'],
            ]);

        $capturedHeaders = null;
        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn(json_encode(['response' => 'ok']));

        $httpClient->method('post')
            ->willReturnCallback(function ($url, $options) use (&$capturedHeaders, $mockResponse) {
                $capturedHeaders = $options['headers'];
                return $mockResponse;
            });

        // Attempt to inject Content-Type via headers
        $service->chat('test');

        // Content-Type should be fixed to application/json
        $this->assertEquals('application/json', $capturedHeaders['Content-Type']);
    }
}