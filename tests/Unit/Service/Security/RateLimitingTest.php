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
 * Security tests for rate limiting and retry behavior
 * 
 * @covers \OCA\TalkBot\Service\OpenAIService
 * @covers \OCA\TalkBot\Service\CustomAIService
 */
class RateLimitingTest extends TestCase
{
    // =============================
    // Exponential Backoff Tests
    // =============================

    public function testOpenAIExponentialBackoffOnRateLimit(): void
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

        // First call: rate limited (429)
        // Second call: success (200)
        $rateLimitResponse = $this->createMock(IResponse::class);
        $rateLimitResponse->method('getStatusCode')->willReturn(429);
        $rateLimitResponse->method('getBody')->willReturn('{"error": {"message": "Rate limit exceeded"}}');

        $successResponse = $this->createMock(IResponse::class);
        $successResponse->method('getStatusCode')->willReturn(200);
        $successResponse->method('getBody')->willReturn(json_encode([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Success']]]
        ]));

        $callCount = 0;
        $httpClient->method('post')
            ->willReturnCallback(function () use (&$callCount, $rateLimitResponse, $successResponse) {
                $callCount++;
                if ($callCount === 1) {
                    return $rateLimitResponse;
                }
                return $successResponse;
            });

        // Logger should warn about rate limit
        $logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('rate limited'));

        $result = $service->chat('test');

        $this->assertEquals('Success', $result);
        $this->assertEquals(2, $callCount); // Initial + 1 retry
    }

    public function testCustomAIExponentialBackoffOnRateLimit(): void
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

        // First call: rate limited (429)
        $rateLimitResponse = $this->createMock(IResponse::class);
        $rateLimitResponse->method('getStatusCode')->willReturn(429);
        $rateLimitResponse->method('getBody')->willReturn('Rate limit');

        // Second call: success (200)
        $successResponse = $this->createMock(IResponse::class);
        $successResponse->method('getStatusCode')->willReturn(200);
        $successResponse->method('getBody')->willReturn(json_encode(['response' => 'Success']));

        $callCount = 0;
        $httpClient->method('post')
            ->willReturnCallback(function () use (&$callCount, $rateLimitResponse, $successResponse) {
                $callCount++;
                if ($callCount === 1) {
                    return $rateLimitResponse;
                }
                return $successResponse;
            });

        $logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('rate limited'));

        $result = $service->chat('test');

        $this->assertEquals('Success', $result);
        $this->assertEquals(2, $callCount);
    }

    // =============================
    // Max Retries Tests
    // =============================

    public function testOpenAIMaxRetriesExceeded(): void
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

        // Always return rate limit
        $rateLimitResponse = $this->createMock(IResponse::class);
        $rateLimitResponse->method('getStatusCode')->willReturn(429);
        $rateLimitResponse->method('getBody')->willReturn('{"error": {"message": "Rate limit"}}');

        $httpClient->method('post')->willReturn($rateLimitResponse);

        // Logger should be called multiple times (once per retry attempt)
        $logger->expects($this->atLeast(3))
            ->method('warning');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('OpenAI request failed after 3 retries');

        $service->chat('test');
    }

    public function testCustomAIMaxRetriesExceeded(): void
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

        // Always return server error
        $errorResponse = $this->createMock(IResponse::class);
        $errorResponse->method('getStatusCode')->willReturn(500);
        $errorResponse->method('getBody')->willReturn('Internal server error');

        $httpClient->method('post')->willReturn($errorResponse);

        // Logger should be called multiple times
        $logger->expects($this->atLeast(3))
            ->method('error');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Custom AI request failed');

        $service->chat('test');
    }

    // =============================
    // Timeout Handling Tests
    // =============================

    public function testOpenAITimeoutIsConfigured(): void
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

        $capturedOptions = null;
        $mockResponse = $this->createMock(IResponse::class);
        $mockResponse->method('getStatusCode')->willReturn(200);
        $mockResponse->method('getBody')->willReturn(json_encode([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Response']]]
        ]));

        $httpClient->method('post')
            ->willReturnCallback(function ($url, $options) use (&$capturedOptions, $mockResponse) {
                $capturedOptions = $options;
                return $mockResponse;
            });

        $service->chat('test');

        // Verify timeout is configured (default 30 seconds)
        $this->assertArrayHasKey('timeout', $capturedOptions);
        $this->assertEquals(30, $capturedOptions['timeout']);
    }

    public function testCustomAITimeoutIsConfigured(): void
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
                ['talk_bot', 'custom_ai_timeout', '30', '60'], // Custom timeout
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

        // Verify custom timeout is used
        $this->assertArrayHasKey('timeout', $capturedOptions);
        $this->assertEquals(60, $capturedOptions['timeout']);
    }

    public function testOpenAITimeoutException(): void
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

        // Simulate timeout
        $httpClient->method('post')
            ->willThrowException(new \Exception('Connection timed out'));

        $logger->expects($this->atLeastOnce())
            ->method('error');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('OpenAI request failed');

        $service->chat('test');
    }

    // =============================
    // Error Code Handling Tests
    // =============================

    public function testOpenAI400ErrorNoRetry(): void
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

        // 400 Bad Request - should not retry
        $badRequestResponse = $this->createMock(IResponse::class);
        $badRequestResponse->method('getStatusCode')->willReturn(400);
        $badRequestResponse->method('getBody')->willReturn('{"error": {"message": "Bad request"}}');

        $httpClient->method('post')->willReturn($badRequestResponse);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('OpenAI API error (400)');

        $service->chat('test');
    }

    public function testOpenAI401ErrorNoRetry(): void
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

        // 401 Unauthorized - should not retry
        $unauthorizedResponse = $this->createMock(IResponse::class);
        $unauthorizedResponse->method('getStatusCode')->willReturn(401);
        $unauthorizedResponse->method('getBody')->willReturn('{"error": {"message": "Invalid API key"}}');

        $httpClient->method('post')->willReturn($unauthorizedResponse);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('OpenAI API error (401)');

        $service->chat('test');
    }

    public function testOpenAI500ErrorWithRetry(): void
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

        // First: 500 error (should retry)
        $errorResponse = $this->createMock(IResponse::class);
        $errorResponse->method('getStatusCode')->willReturn(500);
        $errorResponse->method('getBody')->willReturn('Internal server error');

        // Second: success
        $successResponse = $this->createMock(IResponse::class);
        $successResponse->method('getStatusCode')->willReturn(200);
        $successResponse->method('getBody')->willReturn(json_encode([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Success']]]
        ]));

        $callCount = 0;
        $httpClient->method('post')
            ->willReturnCallback(function () use (&$callCount, $errorResponse, $successResponse) {
                $callCount++;
                if ($callCount === 1) {
                    return $errorResponse;
                }
                return $successResponse;
            });

        $result = $service->chat('test');

        $this->assertEquals('Success', $result);
    }

    public function testOpenAI502ErrorWithRetry(): void
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

        // First: 502 error (should retry)
        $errorResponse = $this->createMock(IResponse::class);
        $errorResponse->method('getStatusCode')->willReturn(502);
        $errorResponse->method('getBody')->willReturn('Bad gateway');

        // Second: success
        $successResponse = $this->createMock(IResponse::class);
        $successResponse->method('getStatusCode')->willReturn(200);
        $successResponse->method('getBody')->willReturn(json_encode([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Success']]]
        ]));

        $callCount = 0;
        $httpClient->method('post')
            ->willReturnCallback(function () use (&$callCount, $errorResponse, $successResponse) {
                $callCount++;
                if ($callCount === 1) {
                    return $errorResponse;
                }
                return $successResponse;
            });

        $result = $service->chat('test');

        $this->assertEquals('Success', $result);
    }

    public function testOpenAI503ErrorWithRetry(): void
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

        // First: 503 error (should retry)
        $errorResponse = $this->createMock(IResponse::class);
        $errorResponse->method('getStatusCode')->willReturn(503);
        $errorResponse->method('getBody')->willReturn('Service unavailable');

        // Second: success
        $successResponse = $this->createMock(IResponse::class);
        $successResponse->method('getStatusCode')->willReturn(200);
        $successResponse->method('getBody')->willReturn(json_encode([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Success']]]
        ]));

        $callCount = 0;
        $httpClient->method('post')
            ->willReturnCallback(function () use (&$callCount, $errorResponse, $successResponse) {
                $callCount++;
                if ($callCount === 1) {
                    return $errorResponse;
                }
                return $successResponse;
            });

        $result = $service->chat('test');

        $this->assertEquals('Success', $result);
    }

    public function testOpenAI504ErrorWithRetry(): void
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

        // First: 504 error (should retry)
        $errorResponse = $this->createMock(IResponse::class);
        $errorResponse->method('getStatusCode')->willReturn(504);
        $errorResponse->method('getBody')->willReturn('Gateway timeout');

        // Second: success
        $successResponse = $this->createMock(IResponse::class);
        $successResponse->method('getStatusCode')->willReturn(200);
        $successResponse->method('getBody')->willReturn(json_encode([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'Success']]]
        ]));

        $callCount = 0;
        $httpClient->method('post')
            ->willReturnCallback(function () use (&$callCount, $errorResponse, $successResponse) {
                $callCount++;
                if ($callCount === 1) {
                    return $errorResponse;
                }
                return $successResponse;
            });

        $result = $service->chat('test');

        $this->assertEquals('Success', $result);
    }

    // =============================
    // Connection Test Rate Limiting
    // =============================

    public function testOpenAITestConnectionRateLimit(): void
    {
        $config = $this->createMock(OpenAIConfigService::class);
        $httpClient = $this->createMock(IClient::class);
        $logger = $this->createMock(LoggerInterface::class);

        $service = new OpenAIService($httpClient, $config, $logger);

        $config->method('getApiKey')->willReturn('sk-test-key');
        $config->method('getOrganizationId')->willReturn('');

        // Test connection should still work even when rate limited briefly
        $rateLimitResponse = $this->createMock(IResponse::class);
        $rateLimitResponse->method('getStatusCode')->willReturn(429);

        $httpClient->method('get')->willReturn($rateLimitResponse);

        $result = $service->testConnection();

        // Rate limit should return false (connection test fails)
        $this->assertFalse($result);
    }

    public function testCustomAITestConnectionServerError(): void
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
                ['talk_bot', 'custom_ai_auth_scheme', 'Bearer', 'Bearer'],
            ]);

        $errorResponse = $this->createMock(IResponse::class);
        $errorResponse->method('getStatusCode')->willReturn(500);

        $httpClient->method('post')->willReturn($errorResponse);

        $result = $service->testConnection();

        $this->assertFalse($result);
    }
}