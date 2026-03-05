<?php

declare(strict_types=1);

namespace OCA\TalkBot\Tests\Unit\Service;

use OCA\TalkBot\Service\CustomAIService;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\TalkBot\Service\CustomAIService
 */
class CustomAIServiceTest extends TestCase
{
    private CustomAIService $service;
    private IClient&MockObject $httpClient;
    private IConfig&MockObject $config;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(IClient::class);
        $this->config = $this->createMock(IConfig::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->service = new CustomAIService($this->httpClient, $this->config, $this->logger);
    }

    // ====================
    // Configuration Tests
    // ====================

    public function testIsConfiguredTrue(): void
    {
        $this->config->method('getAppValue')
            ->with('talk_bot', 'custom_ai_endpoint', '')
            ->willReturn('https://api.custom.com/v1');

        $result = $this->service->isConfigured();

        $this->assertTrue($result);
    }

    public function testIsConfiguredFalse(): void
    {
        $this->config->method('getAppValue')
            ->with('talk_bot', 'custom_ai_endpoint', '')
            ->willReturn('');

        $result = $this->service->isConfigured();

        $this->assertFalse($result);
    }

    public function testGetSetEndpoint(): void
    {
        $this->config->expects($this->once())
            ->method('getAppValue')
            ->with('talk_bot', 'custom_ai_endpoint', '');

        $endpoint = $this->service->getEndpoint();

        $this->config->expects($this->once())
            ->method('setAppValue')
            ->with('talk_bot', 'custom_ai_endpoint', 'https://new.endpoint.com');

        $this->service->setEndpoint('https://new.endpoint.com');
    }

    public function testGetSetApiKey(): void
    {
        $this->config->expects($this->once())
            ->method('getAppValue')
            ->with('talk_bot', 'custom_ai_api_key', '');

        $apiKey = $this->service->getApiKey();

        $this->config->expects($this->once())
            ->method('setAppValue')
            ->with('talk_bot', 'custom_ai_api_key', 'new-key');

        $this->service->setApiKey('new-key');
    }

    public function testGetSetModel(): void
    {
        $this->config->method('getAppValue')
            ->with('talk_bot', 'custom_ai_model', '')
            ->willReturn('custom-model-v1');

        $model = $this->service->getModel();

        $this->assertEquals('custom-model-v1', $model);

        $this->config->expects($this->once())
            ->method('setAppValue')
            ->with('talk_bot', 'custom_ai_model', 'new-model');

        $this->service->setModel('new-model');
    }

    public function testGetSetHeaders(): void
    {
        $this->config->method('getAppValue')
            ->with('talk_bot', 'custom_ai_headers', '{}')
            ->willReturn('{"X-Custom":"value"}');

        $headers = $this->service->getHeaders();

        $this->assertIsArray($headers);

        $this->config->expects($this->once())
            ->method('setAppValue')
            ->with('talk_bot', 'custom_ai_headers', '{"X-New":"header"}');

        $this->service->setHeaders(['X-New' => 'header']);
    }

    public function testGetSetTimeout(): void
    {
        $this->config->method('getAppValue')
            ->with('talk_bot', 'custom_ai_timeout', '30')
            ->willReturn('60');

        $timeout = $this->service->getTimeout();

        $this->assertEquals(60, $timeout);

        $this->config->expects($this->once())
            ->method('setAppValue')
            ->with('talk_bot', 'custom_ai_timeout', '120');

        $this->service->setTimeout(120);
    }

    // ==============
    // Chat Tests
    // ==============

    public function testChatSuccess(): void
    {
        $endpoint = 'https://api.custom.com/v1/chat';
        $prompt = 'Hello';

        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'custom_ai_endpoint', '', $endpoint],
                ['talk_bot', 'custom_ai_api_key', '', 'test-key'],
                ['talk_bot', 'custom_ai_model', '', 'custom-model'],
                ['talk_bot', 'custom_ai_headers', '{}', '{}'],
                ['talk_bot', 'custom_ai_timeout', '30', '30'],
                ['talk_bot', 'custom_ai_auth_scheme', 'Bearer', 'Bearer'],
            ]);

        $responseBody = json_encode([
            'choices' => [
                ['message' => ['role' => 'assistant', 'content' => 'Hello back!']]
            ]
        ]);

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn($responseBody);

        $this->httpClient->method('post')->willReturn($mockResponse);

        $result = $this->service->chat($prompt);

        $this->assertEquals('Hello back!', $result);
    }

    public function testChatNoEndpoint(): void
    {
        $this->config->method('getAppValue')
            ->with('talk_bot', 'custom_ai_endpoint', '')
            ->willReturn('');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Custom AI provider not configured');

        $this->service->chat('Hello');
    }

    public function testChatWithHistory(): void
    {
        $endpoint = 'https://api.custom.com/v1/chat';
        $prompt = 'Continue';
        $history = [
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi!']
        ];

        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'custom_ai_endpoint', '', $endpoint],
                ['talk_bot', 'custom_ai_api_key', '', 'test-key'],
                ['talk_bot', 'custom_ai_model', '', 'custom-model'],
                ['talk_bot', 'custom_ai_headers', '{}', '{}'],
                ['talk_bot', 'custom_ai_timeout', '30', '30'],
                ['talk_bot', 'custom_ai_auth_scheme', 'Bearer', 'Bearer'],
            ]);

        $capturedPayload = null;
        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn(json_encode([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Ok']]]
        ]));

        $this->httpClient->method('post')
            ->willReturnCallback(function ($url, $options) use (&$capturedPayload, $mockResponse) {
                $capturedPayload = json_decode($options['body'], true);
                return $mockResponse;
            });

        $this->service->chat($prompt, $history);

        $this->assertCount(3, $capturedPayload['messages']);
    }

    // ====================
    // Response Parsing Tests
    // ====================

    public function testParseResponseOpenAIFormat(): void
    {
        $endpoint = 'https://api.custom.com/v1/chat';

        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'custom_ai_endpoint', '', $endpoint],
                ['talk_bot', 'custom_ai_api_key', '', ''],
                ['talk_bot', 'custom_ai_model', '', 'model'],
                ['talk_bot', 'custom_ai_headers', '{}', '{}'],
                ['talk_bot', 'custom_ai_timeout', '30', '30'],
            ]);

        $responseBody = json_encode([
            'choices' => [
                ['message' => ['content' => 'OpenAI format response']]
            ]
        ]);

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn($responseBody);

        $this->httpClient->method('post')->willReturn($mockResponse);

        $result = $this->service->chat('test');

        $this->assertEquals('OpenAI format response', $result);
    }

    public function testParseResponseCustomFormat(): void
    {
        $endpoint = 'https://api.custom.com/v1/chat';

        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'custom_ai_endpoint', '', $endpoint],
                ['talk_bot', 'custom_ai_api_key', '', ''],
                ['talk_bot', 'custom_ai_model', '', 'model'],
                ['talk_bot', 'custom_ai_headers', '{}', '{}'],
                ['talk_bot', 'custom_ai_timeout', '30', '30'],
            ]);

        $responseBody = json_encode([
            'response' => 'Custom format response'
        ]);

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn($responseBody);

        $this->httpClient->method('post')->willReturn($mockResponse);

        $result = $this->service->chat('test');

        $this->assertEquals('Custom format response', $result);
    }

    public function testParseResponseTextField(): void
    {
        $endpoint = 'https://api.custom.com/v1/chat';

        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'custom_ai_endpoint', '', $endpoint],
                ['talk_bot', 'custom_ai_api_key', '', ''],
                ['talk_bot', 'custom_ai_model', '', 'model'],
                ['talk_bot', 'custom_ai_headers', '{}', '{}'],
                ['talk_bot', 'custom_ai_timeout', '30', '30'],
            ]);

        $responseBody = json_encode([
            'text' => 'Text field response'
        ]);

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn($responseBody);

        $this->httpClient->method('post')->willReturn($mockResponse);

        $result = $this->service->chat('test');

        $this->assertEquals('Text field response', $result);
    }

    public function testParseResponseContentField(): void
    {
        $endpoint = 'https://api.custom.com/v1/chat';

        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'custom_ai_endpoint', '', $endpoint],
                ['talk_bot', 'custom_ai_api_key', '', ''],
                ['talk_bot', 'custom_ai_model', '', 'model'],
                ['talk_bot', 'custom_ai_headers', '{}', '{}'],
                ['talk_bot', 'custom_ai_timeout', '30', '30'],
            ]);

        $responseBody = json_encode([
            'content' => 'Content field response'
        ]);

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn($responseBody);

        $this->httpClient->method('post')->willReturn($mockResponse);

        $result = $this->service->chat('test');

        $this->assertEquals('Content field response', $result);
    }

    public function testParseResponseRawBody(): void
    {
        $endpoint = 'https://api.custom.com/v1/chat';

        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'custom_ai_endpoint', '', $endpoint],
                ['talk_bot', 'custom_ai_api_key', '', ''],
                ['talk_bot', 'custom_ai_model', '', 'model'],
                ['talk_bot', 'custom_ai_headers', '{}', '{}'],
                ['talk_bot', 'custom_ai_timeout', '30', '30'],
            ]);

        $responseBody = 'Raw text response';

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn($responseBody);

        $this->httpClient->method('post')->willReturn($mockResponse);

        $result = $this->service->chat('test');

        $this->assertEquals('Raw text response', $result);
    }

    // ==================
    // Retry Tests
    // ==================

    public function testChatRetryOnServerError(): void
    {
        $endpoint = 'https://api.custom.com/v1/chat';

        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'custom_ai_endpoint', '', $endpoint],
                ['talk_bot', 'custom_ai_api_key', '', ''],
                ['talk_bot', 'custom_ai_model', '', 'model'],
                ['talk_bot', 'custom_ai_headers', '{}', '{}'],
                ['talk_bot', 'custom_ai_timeout', '30', '30'],
            ]);

        // First call: 500 error
        $errorResponse = $this->createMock(IResponse::class);
        $errorResponse->method('getStatusCode')->willReturn(500);
        $errorResponse->method('getBody')->willReturn('Internal server error');

        // Second call: success
        $successResponse = $this->createMock(IResponse::class);
        $successResponse->method('getStatusCode')->willReturn(200);
        $successResponse->method('getBody')->willReturn(json_encode(['response' => 'Success']));

        $callCount = 0;
        $this->httpClient->method('post')
            ->willReturnCallback(function () use (&$callCount, $errorResponse, $successResponse) {
                $callCount++;
                if ($callCount === 1) {
                    return $errorResponse;
                }
                return $successResponse;
            });

        $this->logger->expects($this->once())
            ->method('warning');

        $result = $this->service->chat('test');

        $this->assertEquals('Success', $result);
    }

    public function testChatRetryOnRateLimit(): void
    {
        $endpoint = 'https://api.custom.com/v1/chat';

        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'custom_ai_endpoint', '', $endpoint],
                ['talk_bot', 'custom_ai_api_key', '', ''],
                ['talk_bot', 'custom_ai_model', '', 'model'],
                ['talk_bot', 'custom_ai_headers', '{}', '{}'],
                ['talk_bot', 'custom_ai_timeout', '30', '30'],
            ]);

        // First call: 429 rate limit
        $rateLimitResponse = $this->createMock(IResponse::class);
        $rateLimitResponse->method('getStatusCode')->willReturn(429);
        $rateLimitResponse->method('getBody')->willReturn('Rate limited');

        // Second call: success
        $successResponse = $this->createMock(IResponse::class);
        $successResponse->method('getStatusCode')->willReturn(200);
        $successResponse->method('getBody')->willReturn(json_encode(['response' => 'Ok']));

        $callCount = 0;
        $this->httpClient->method('post')
            ->willReturnCallback(function () use (&$callCount, $rateLimitResponse, $successResponse) {
                $callCount++;
                if ($callCount === 1) {
                    return $rateLimitResponse;
                }
                return $successResponse;
            });

        $result = $this->service->chat('test');

        $this->assertEquals('Ok', $result);
    }

    public function testChatMaxRetriesExceeded(): void
    {
        $endpoint = 'https://api.custom.com/v1/chat';

        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'custom_ai_endpoint', '', $endpoint],
                ['talk_bot', 'custom_ai_api_key', '', ''],
                ['talk_bot', 'custom_ai_model', '', 'model'],
                ['talk_bot', 'custom_ai_headers', '{}', '{}'],
                ['talk_bot', 'custom_ai_timeout', '30', '30'],
            ]);

        $errorResponse = $this->createMock(IResponse::class);
        $errorResponse->method('getStatusCode')->willReturn(500);
        $errorResponse->method('getBody')->willReturn('Server error');

        $this->httpClient->method('post')->willReturn($errorResponse);

        $this->logger->expects($this->atLeast(3))
            ->method('error');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Custom AI request failed');

        $this->service->chat('test');
    }

    // ========================
    // testConnection Tests
    // ========================

    public function testTestConnectionSuccess(): void
    {
        $endpoint = 'https://api.custom.com/v1/chat';

        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'custom_ai_endpoint', '', $endpoint],
                ['talk_bot', 'custom_ai_api_key', '', 'key'],
                ['talk_bot', 'custom_ai_model', '', 'model'],
                ['talk_bot', 'custom_ai_headers', '{}', '{}'],
                ['talk_bot', 'custom_ai_auth_scheme', 'Bearer', 'Bearer'],
            ]);

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);

        $this->httpClient->method('post')->willReturn($mockResponse);

        $result = $this->service->testConnection();

        $this->assertTrue($result);
    }

    public function testTestConnectionBadRequest(): void
    {
        $endpoint = 'https://api.custom.com/v1/chat';

        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'custom_ai_endpoint', '', $endpoint],
                ['talk_bot', 'custom_ai_api_key', '', 'key'],
                ['talk_bot', 'custom_ai_model', '', 'model'],
                ['talk_bot', 'custom_ai_headers', '{}', '{}'],
                ['talk_bot', 'custom_ai_auth_scheme', 'Bearer', 'Bearer'],
            ]);

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(400);

        $this->httpClient->method('post')->willReturn($mockResponse);

        // 400 is still a valid response (server is reachable)
        $result = $this->service->testConnection();

        $this->assertFalse($result);
    }

    public function testTestConnectionNoEndpoint(): void
    {
        $this->config->method('getAppValue')
            ->with('talk_bot', 'custom_ai_endpoint', '')
            ->willReturn('');

        $result = $this->service->testConnection();

        $this->assertFalse($result);
    }

    public function testTestConnectionException(): void
    {
        $endpoint = 'https://api.custom.com/v1/chat';

        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'custom_ai_endpoint', '', $endpoint],
                ['talk_bot', 'custom_ai_api_key', '', 'key'],
                ['talk_bot', 'custom_ai_model', '', 'model'],
                ['talk_bot', 'custom_ai_headers', '{}', '{}'],
                ['talk_bot', 'custom_ai_auth_scheme', 'Bearer', 'Bearer'],
            ]);

        $this->httpClient->method('post')
            ->willThrowException(new \Exception('Network error'));

        $this->logger->expects($this->once())
            ->method('error');

        $result = $this->service->testConnection();

        $this->assertFalse($result);
    }

    // ====================
    // getSettings Tests
    // ====================

    public function testGetSettings(): void
    {
        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'custom_ai_endpoint', '', 'https://api.custom.com'],
                ['talk_bot', 'custom_ai_model', '', 'model-v1'],
                ['talk_bot', 'custom_ai_api_key', '', 'secret-key'],
                ['talk_bot', 'custom_ai_headers', '{}', '{"X-Custom":"value"}'],
                ['talk_bot', 'custom_ai_timeout', '30', '60'],
            ]);

        $settings = $this->service->getSettings();

        $this->assertArrayHasKey('endpoint', $settings);
        $this->assertArrayHasKey('model', $settings);
        $this->assertArrayHasKey('hasApiKey', $settings);
        $this->assertArrayHasKey('headers', $settings);
        $this->assertArrayHasKey('timeout', $settings);

        $this->assertEquals('https://api.custom.com', $settings['endpoint']);
        $this->assertEquals('model-v1', $settings['model']);
        $this->assertTrue($settings['hasApiKey']);
    }

    // =====================
    // Header Building Tests
    // =====================

    public function testBuildHeadersWithApiKey(): void
    {
        $endpoint = 'https://api.custom.com/v1/chat';

        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'custom_ai_endpoint', '', $endpoint],
                ['talk_bot', 'custom_ai_api_key', '', 'test-key'],
                ['talk_bot', 'custom_ai_model', '', 'model'],
                ['talk_bot', 'custom_ai_headers', '{}', '{}'],
                ['talk_bot', 'custom_ai_timeout', '30', '30'],
                ['talk_bot', 'custom_ai_auth_scheme', 'Bearer', 'Bearer'],
            ]);

        $capturedHeaders = null;
        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn(json_encode(['response' => 'ok']));

        $this->httpClient->method('post')
            ->willReturnCallback(function ($url, $options) use (&$capturedHeaders, $mockResponse) {
                $capturedHeaders = $options['headers'];
                return $mockResponse;
            });

        $this->service->chat('test');

        $this->assertArrayHasKey('Authorization', $capturedHeaders);
        $this->assertEquals('Bearer test-key', $capturedHeaders['Authorization']);
    }

    public function testBuildHeadersWithCustomAuthScheme(): void
    {
        $endpoint = 'https://api.custom.com/v1/chat';

        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'custom_ai_endpoint', '', $endpoint],
                ['talk_bot', 'custom_ai_api_key', '', 'test-key'],
                ['talk_bot', 'custom_ai_model', '', 'model'],
                ['talk_bot', 'custom_ai_headers', '{}', '{}'],
                ['talk_bot', 'custom_ai_timeout', '30', '30'],
                ['talk_bot', 'custom_ai_auth_scheme', 'Bearer', 'ApiKey'],
            ]);

        $capturedHeaders = null;
        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn(json_encode(['response' => 'ok']));

        $this->httpClient->method('post')
            ->willReturnCallback(function ($url, $options) use (&$capturedHeaders, $mockResponse) {
                $capturedHeaders = $options['headers'];
                return $mockResponse;
            });

        $this->service->chat('test');

        $this->assertArrayHasKey('Authorization', $capturedHeaders);
        $this->assertEquals('ApiKey test-key', $capturedHeaders['Authorization']);
    }

    public function testBuildHeadersWithCustomHeaders(): void
    {
        $endpoint = 'https://api.custom.com/v1/chat';

        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'custom_ai_endpoint', '', $endpoint],
                ['talk_bot', 'custom_ai_api_key', '', ''],
                ['talk_bot', 'custom_ai_model', '', 'model'],
                ['talk_bot', 'custom_ai_headers', '{}', '{"X-API-Version":"2.0","X-Custom":"value"}'],
                ['talk_bot', 'custom_ai_timeout', '30', '30'],
                ['talk_bot', 'custom_ai_auth_scheme', 'Bearer', 'Bearer'],
            ]);

        $capturedHeaders = null;
        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn(json_encode(['response' => 'ok']));

        $this->httpClient->method('post')
            ->willReturnCallback(function ($url, $options) use (&$capturedHeaders, $mockResponse) {
                $capturedHeaders = $options['headers'];
                return $mockResponse;
            });

        $this->service->chat('test');

        $this->assertArrayHasKey('X-API-Version', $capturedHeaders);
        $this->assertEquals('2.0', $capturedHeaders['X-API-Version']);
        $this->assertArrayHasKey('X-Custom', $capturedHeaders);
        $this->assertEquals('value', $capturedHeaders['X-Custom']);
    }

    // ===============
    // Edge Cases
    // ===============

    public function testChatWithoutApiKey(): void
    {
        $endpoint = 'https://api.custom.com/v1/chat';

        $this->config->method('getAppValue')
            ->willReturnMap([
                ['talk_bot', 'custom_ai_endpoint', '', $endpoint],
                ['talk_bot', 'custom_ai_api_key', '', ''],
                ['talk_bot', 'custom_ai_model', '', 'model'],
                ['talk_bot', 'custom_ai_headers', '{}', '{}'],
                ['talk_bot', 'custom_ai_timeout', '30', '30'],
                ['talk_bot', 'custom_ai_auth_scheme', 'Bearer', 'Bearer'],
            ]);

        $capturedHeaders = null;
        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn(json_encode(['response' => 'ok']));

        $this->httpClient->method('post')
            ->willReturnCallback(function ($url, $options) use (&$capturedHeaders, $mockResponse) {
                $capturedHeaders = $options['headers'];
                return $mockResponse;
            });

        $this->service->chat('test');

        // No Authorization header should be present
        $this->assertArrayNotHasKey('Authorization', $capturedHeaders);
    }
}