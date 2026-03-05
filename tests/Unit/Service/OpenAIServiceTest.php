<?php

declare(strict_types=1);

namespace OCA\TalkBot\Tests\Unit\Service;

use OCA\TalkBot\Service\OpenAIService;
use OCA\TalkBot\Service\OpenAIConfigService;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IResponse;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\TalkBot\Service\OpenAIService
 */
class OpenAIServiceTest extends TestCase
{
    private OpenAIService $service;
    private IClient&MockObject $httpClient;
    private OpenAIConfigService&MockObject $config;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(IClient::class);
        $this->config = $this->createMock(OpenAIConfigService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->service = new OpenAIService($this->httpClient, $this->config, $this->logger);
    }

    // ================
    // Chat Tests
    // ================

    public function testChatSuccess(): void
    {
        $apiKey = 'sk-test-1234567890';
        $model = 'gpt-4';
        $prompt = 'Hello, how are you?';

        $this->config->method('getApiKey')->willReturn($apiKey);
        $this->config->method('getDefaultModel')->willReturn($model);
        $this->config->method('getTemperature')->willReturn(0.7);
        $this->config->method('getMaxTokens')->willReturn(1024);
        $this->config->method('getOrganizationId')->willReturn('');
        $this->config->method('getSystemPrompt')->willReturn('');

        $responseBody = json_encode([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'I am doing well, thank you for asking!'
                    ]
                ]
            ]
        ]);

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn($responseBody);

        $this->httpClient->method('post')
            ->willReturn($mockResponse);

        $result = $this->service->chat($prompt);

        $this->assertEquals('I am doing well, thank you for asking!', $result);
    }

    public function testChatNoApiKey(): void
    {
        $this->config->method('getApiKey')->willReturn('');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('OpenAI API key not configured');

        $this->service->chat('Hello');
    }

    public function testChatWithHistory(): void
    {
        $apiKey = 'sk-test-1234567890';
        $model = 'gpt-4';
        $prompt = 'What did I just say?';
        $history = [
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there!']
        ];

        $this->config->method('getApiKey')->willReturn($apiKey);
        $this->config->method('getDefaultModel')->willReturn($model);
        $this->config->method('getTemperature')->willReturn(0.7);
        $this->config->method('getMaxTokens')->willReturn(1024);
        $this->config->method('getOrganizationId')->willReturn('');
        $this->config->method('getSystemPrompt')->willReturn('');

        $responseBody = json_encode([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'You said "Hello".'
                    ]
                ]
            ]
        ]);

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn($responseBody);

        $this->httpClient->method('post')
            ->willReturn($mockResponse);

        $result = $this->service->chat($prompt, $history);

        $this->assertEquals('You said "Hello".', $result);
    }

    public function testChatWithSystemPrompt(): void
    {
        $apiKey = 'sk-test-1234567890';
        $model = 'gpt-4';
        $prompt = 'Hello';

        $this->config->method('getApiKey')->willReturn($apiKey);
        $this->config->method('getDefaultModel')->willReturn($model);
        $this->config->method('getTemperature')->willReturn(0.7);
        $this->config->method('getMaxTokens')->willReturn(1024);
        $this->config->method('getOrganizationId')->willReturn('');
        $this->config->method('getSystemPrompt')->willReturn('You are a helpful assistant.');

        $responseBody = json_encode([
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello! How can I help you today?'
                    ]
                ]
            ]
        ]);

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn($responseBody);

        $this->httpClient->method('post')
            ->willReturn($mockResponse);

        $result = $this->service->chat($prompt);

        $this->assertEquals('Hello! How can I help you today?', $result);
    }

    // =====================
    // Retry Logic Tests
    // =====================

    public function testChatRetryOnRateLimit(): void
    {
        $apiKey = 'sk-test-1234567890';
        $model = 'gpt-4';
        $prompt = 'Hello';

        $this->config->method('getApiKey')->willReturn($apiKey);
        $this->config->method('getDefaultModel')->willReturn($model);
        $this->config->method('getTemperature')->willReturn(0.7);
        $this->config->method('getMaxTokens')->willReturn(1024);
        $this->config->method('getOrganizationId')->willReturn('');
        $this->config->method('getSystemPrompt')->willReturn('');

        // First call: rate limited (429)
        $rateLimitResponse = $this->createMock(IResponse::class);
        $rateLimitResponse->method('getStatusCode')->willReturn(429);
        $rateLimitResponse->method('getBody')->willReturn('{"error": {"message": "Rate limit exceeded"}}');

        // Second call: success
        $successResponse = $this->createMock(IResponse::class);
        $successResponse->method('getStatusCode')->willReturn(200);
        $successResponse->method('getBody')->willReturn(json_encode([
            'choices' => [
                ['message' => ['role' => 'assistant', 'content' => 'Retry successful']]
            ]
        ]));

        $callCount = 0;
        $this->httpClient->method('post')
            ->willReturnCallback(function () use (&$callCount, $rateLimitResponse, $successResponse) {
                $callCount++;
                if ($callCount === 1) {
                    return $rateLimitResponse;
                }
                return $successResponse;
            });

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('rate limited'));

        $result = $this->service->chat($prompt);

        $this->assertEquals('Retry successful', $result);
    }

    public function testChatRetryOnServerError(): void
    {
        $apiKey = 'sk-test-1234567890';
        $model = 'gpt-4';
        $prompt = 'Hello';

        $this->config->method('getApiKey')->willReturn($apiKey);
        $this->config->method('getDefaultModel')->willReturn($model);
        $this->config->method('getTemperature')->willReturn(0.7);
        $this->config->method('getMaxTokens')->willReturn(1024);
        $this->config->method('getOrganizationId')->willReturn('');
        $this->config->method('getSystemPrompt')->willReturn('');

        // First call: server error (500)
        $errorResponse = $this->createMock(IResponse::class);
        $errorResponse->method('getStatusCode')->willReturn(500);
        $errorResponse->method('getBody')->willReturn('{"error": {"message": "Internal server error"}}');

        // Second call: success
        $successResponse = $this->createMock(IResponse::class);
        $successResponse->method('getStatusCode')->willReturn(200);
        $successResponse->method('getBody')->willReturn(json_encode([
            'choices' => [
                ['message' => ['role' => 'assistant', 'content' => 'Success after retry']]
            ]
        ]));

        $callCount = 0;
        $this->httpClient->method('post')
            ->willReturnCallback(function () use (&$callCount, $errorResponse, $successResponse) {
                $callCount++;
                if ($callCount === 1) {
                    return $errorResponse;
                }
                return $successResponse;
            });

        $result = $this->service->chat($prompt);

        $this->assertEquals('Success after retry', $result);
    }

    public function testChatMaxRetriesExceeded(): void
    {
        $apiKey = 'sk-test-1234567890';
        $model = 'gpt-4';
        $prompt = 'Hello';

        $this->config->method('getApiKey')->willReturn($apiKey);
        $this->config->method('getDefaultModel')->willReturn($model);
        $this->config->method('getTemperature')->willReturn(0.7);
        $this->config->method('getMaxTokens')->willReturn(1024);
        $this->config->method('getOrganizationId')->willReturn('');
        $this->config->method('getSystemPrompt')->willReturn('');

        $errorResponse = $this->createMock(IResponse::class);
        $errorResponse->method('getStatusCode')->willReturn(429);
        $errorResponse->method('getBody')->willReturn('{"error": {"message": "Rate limit"}}');

        $this->httpClient->method('post')->willReturn($errorResponse);

        $this->logger->expects($this->atLeast(3))
            ->method('warning');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('OpenAI request failed after 3 retries');

        $this->service->chat($prompt);
    }

    // =========================
    // Error Response Tests
    // =========================

    public function testChatInvalidResponseFormat(): void
    {
        $apiKey = 'sk-test-1234567890';
        $model = 'gpt-4';
        $prompt = 'Hello';

        $this->config->method('getApiKey')->willReturn($apiKey);
        $this->config->method('getDefaultModel')->willReturn($model);
        $this->config->method('getTemperature')->willReturn(0.7);
        $this->config->method('getMaxTokens')->willReturn(1024);
        $this->config->method('getOrganizationId')->willReturn('');
        $this->config->method('getSystemPrompt')->willReturn('');

        $responseBody = json_encode([
            'error' => 'missing choices'
        ]);

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn($responseBody);

        $this->httpClient->method('post')->willReturn($mockResponse);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid response format');

        $this->service->chat($prompt);
    }

    public function testChatErrorResponse(): void
    {
        $apiKey = 'sk-test-1234567890';
        $model = 'gpt-4';
        $prompt = 'Hello';

        $this->config->method('getApiKey')->willReturn($apiKey);
        $this->config->method('getDefaultModel')->willReturn($model);
        $this->config->method('getTemperature')->willReturn(0.7);
        $this->config->method('getMaxTokens')->willReturn(1024);
        $this->config->method('getOrganizationId')->willReturn('');
        $this->config->method('getSystemPrompt')->willReturn('');

        $responseBody = json_encode([
            'error' => [
                'message' => 'Invalid API key'
            ]
        ]);

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(401);
        $mockResponse->method('getBody')->willReturn($responseBody);

        $this->httpClient->method('post')->willReturn($mockResponse);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('OpenAI API error');

        $this->service->chat($prompt);
    }

    // ============================
    // testConnection Tests
    // ============================

    public function testTestConnectionSuccess(): void
    {
        $apiKey = 'sk-test-1234567890';

        $this->config->method('getApiKey')->willReturn($apiKey);
        $this->config->method('getOrganizationId')->willReturn('');

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);

        $this->httpClient->method('get')->willReturn($mockResponse);

        $result = $this->service->testConnection();

        $this->assertTrue($result);
    }

    public function testTestConnectionFailure(): void
    {
        $apiKey = 'sk-test-1234567890';

        $this->config->method('getApiKey')->willReturn($apiKey);
        $this->config->method('getOrganizationId')->willReturn('');

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(401);

        $this->httpClient->method('get')->willReturn($mockResponse);

        $result = $this->service->testConnection();

        $this->assertFalse($result);
    }

    public function testTestConnectionNoApiKey(): void
    {
        $this->config->method('getApiKey')->willReturn('');

        $result = $this->service->testConnection();

        $this->assertFalse($result);
    }

    public function testTestConnectionException(): void
    {
        $apiKey = 'sk-test-1234567890';

        $this->config->method('getApiKey')->willReturn($apiKey);
        $this->config->method('getOrganizationId')->willReturn('');

        $this->httpClient->method('get')
            ->willThrowException(new \Exception('Network error'));

        $this->logger->expects($this->once())
            ->method('error');

        $result = $this->service->testConnection();

        $this->assertFalse($result);
    }

    // =====================
    // listModels Tests
    // =====================

    public function testListModelsSuccess(): void
    {
        $apiKey = 'sk-test-1234567890';

        $this->config->method('getApiKey')->willReturn($apiKey);

        $responseBody = json_encode([
            'data' => [
                ['id' => 'gpt-4', 'object' => 'model'],
                ['id' => 'gpt-3.5-turbo', 'object' => 'model'],
            ]
        ]);

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn($responseBody);

        $this->httpClient->method('get')->willReturn($mockResponse);

        $models = $this->service->listModels();

        $this->assertCount(2, $models);
        $this->assertEquals('gpt-4', $models[0]['id']);
    }

    public function testListModelsNoApiKey(): void
    {
        $this->config->method('getApiKey')->willReturn('');

        $models = $this->service->listModels();

        $this->assertEmpty($models);
    }

    public function testListModelsError(): void
    {
        $apiKey = 'sk-test-1234567890';

        $this->config->method('getApiKey')->willReturn($apiKey);

        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(500);
        $mockResponse->method('getBody')->willReturn('{"error": "server error"}');

        $this->httpClient->method('get')->willReturn($mockResponse);

        $this->logger->expects($this->once())
            ->method('error');

        $models = $this->service->listModels();

        $this->assertEmpty($models);
    }

    // =====================
    // buildMessages Tests
    // =====================

    public function testBuildMessagesWithHistory(): void
    {
        $apiKey = 'sk-test-1234567890';
        $model = 'gpt-4';
        $prompt = 'Continue';
        $history = [
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there!']
        ];

        $this->config->method('getApiKey')->willReturn($apiKey);
        $this->config->method('getDefaultModel')->willReturn($model);
        $this->config->method('getTemperature')->willReturn(0.7);
        $this->config->method('getMaxTokens')->willReturn(1024);
        $this->config->method('getOrganizationId')->willReturn('');
        $this->config->method('getSystemPrompt')->willReturn('');

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

        $this->assertCount(3, $capturedPayload['messages']); // 2 history + 1 new
        $this->assertEquals('user', $capturedPayload['messages'][0]['role']);
        $this->assertEquals('Hello', $capturedPayload['messages'][0]['content']);
        $this->assertEquals('assistant', $capturedPayload['messages'][1]['role']);
        $this->assertEquals('Hi there!', $capturedPayload['messages'][1]['content']);
        $this->assertEquals('user', $capturedPayload['messages'][2]['role']);
        $this->assertEquals('Continue', $capturedPayload['messages'][2]['content']);
    }

    // ===============
    // Edge Cases
    // ===============

    public function testChatWithOrganizationId(): void
    {
        $apiKey = 'sk-test-1234567890';
        $orgId = 'org-12345';
        $model = 'gpt-4';
        $prompt = 'Hello';

        $this->config->method('getApiKey')->willReturn($apiKey);
        $this->config->method('getDefaultModel')->willReturn($model);
        $this->config->method('getTemperature')->willReturn(0.7);
        $this->config->method('getMaxTokens')->willReturn(1024);
        $this->config->method('getOrganizationId')->willReturn($orgId);
        $this->config->method('getSystemPrompt')->willReturn('');

        $capturedHeaders = null;
        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn(json_encode([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Hi']]]
        ]));

        $this->httpClient->method('post')
            ->willReturnCallback(function ($url, $options) use (&$capturedHeaders, $mockResponse) {
                $capturedHeaders = $options['headers'];
                return $mockResponse;
            });

        $this->service->chat($prompt);

        $this->assertArrayHasKey('OpenAI-Organization', $capturedHeaders);
        $this->assertEquals($orgId, $capturedHeaders['OpenAI-Organization']);
    }
}