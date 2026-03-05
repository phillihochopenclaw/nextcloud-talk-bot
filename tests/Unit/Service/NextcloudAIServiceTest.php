<?php

declare(strict_types=1);

namespace OCA\TalkBot\Tests\Unit\Service;

use OCA\TalkBot\Service\NextcloudAIService;
use OCP\TextProcessing\IManager;
use OCP\TextProcessing\Task;
use OCP\TextProcessing\ChatContext;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \OCA\TalkBot\Service\NextcloudAIService
 */
class NextcloudAIServiceTest extends TestCase
{
    private NextcloudAIService $service;
    private IManager&MockObject $textProcessingManager;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->textProcessingManager = $this->createMock(IManager::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->service = new NextcloudAIService($this->textProcessingManager, $this->logger);
    }

    // =====================
    // isAvailable Tests
    // =====================

    public function testIsAvailableTrue(): void
    {
        $this->textProcessingManager->method('hasProviders')
            ->willReturn(true);

        $result = $this->service->isAvailable();

        $this->assertTrue($result);
    }

    public function testIsAvailableFalse(): void
    {
        $this->textProcessingManager->method('hasProviders')
            ->willReturn(false);

        $result = $this->service->isAvailable();

        $this->assertFalse($result);
    }

    public function testIsAvailableHandlesException(): void
    {
        $this->textProcessingManager->method('hasProviders')
            ->willThrowException(new \Exception('Provider error'));

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('availability check failed'));

        $result = $this->service->isAvailable();

        $this->assertFalse($result);
    }

    // =============================
    // getAvailableTaskTypes Tests
    // =============================

    public function testGetAvailableTaskTypes(): void
    {
        $mockProvider1 = new class {
            public function getName(): string { return 'Local AI'; }
            public function getTaskType(): string { return 'chat'; }
        };
        $mockProvider2 = new class {
            public function getName(): string { return 'OpenAI Provider'; }
            public function getTaskType(): string { return 'chat'; }
        };

        $this->textProcessingManager->method('getProviders')
            ->willReturn([$mockProvider1, $mockProvider2]);

        $types = $this->service->getAvailableTaskTypes();

        $this->assertCount(2, $types);
        $this->assertEquals('Local AI', $types[0]['name']);
        $this->assertEquals('chat', $types[0]['taskType']);
    }

    public function testGetAvailableTaskTypesEmpty(): void
    {
        $this->textProcessingManager->method('getProviders')
            ->willReturn([]);

        $types = $this->service->getAvailableTaskTypes();

        $this->assertEmpty($types);
    }

    public function testGetAvailableTaskTypesHandlesException(): void
    {
        $this->textProcessingManager->method('getProviders')
            ->willThrowException(new \Exception('Failed to get providers'));

        $this->logger->expects($this->once())
            ->method('warning');

        $types = $this->service->getAvailableTaskTypes();

        $this->assertEmpty($types);
    }

    // ====================
    // processChat Tests
    // ====================

    public function testProcessChatSuccess(): void
    {
        // Note: This test requires mocking Task class which is final in Nextcloud
        // In real implementation, we would use integration tests or a wrapper
        
        $this->textProcessingManager->method('hasProviders')
            ->willReturn(true);

        // Since Task is a real object, we need to test this differently
        // This is a placeholder for integration testing
        $this->markTestSkipped('Task class requires integration testing');
    }

    public function testProcessChatNoProvider(): void
    {
        $this->textProcessingManager->method('hasProviders')
            ->willReturn(false);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Nextcloud AI is not available');

        $this->service->processChat('Hello');
    }

    public function testProcessChatWithException(): void
    {
        $this->textProcessingManager->method('hasProviders')
            ->willReturn(true);

        // This test would need proper Task mocking in integration tests
        $this->markTestSkipped('Task class requires integration testing');
    }

    // ==========================
    // processChatAsync Tests
    // ==========================

    public function testProcessChatAsyncNoProvider(): void
    {
        $this->textProcessingManager->method('hasProviders')
            ->willReturn(false);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Nextcloud AI is not available');

        $this->service->processChatAsync('Hello');
    }

    // =========================
    // createChatContext Tests
    // =========================

    public function testCreateChatContext(): void
    {
        $history = [
            ['role' => 'user', 'content' => 'Hello'],
            ['role' => 'assistant', 'content' => 'Hi there!'],
        ];

        $context = $this->service->createChatContext($history);

        $this->assertInstanceOf(ChatContext::class, $context);
    }

    public function testCreateChatContextEmpty(): void
    {
        $context = $this->service->createChatContext([]);

        $this->assertInstanceOf(ChatContext::class, $context);
    }

    // ========================
    // Task Status Tests
    // ========================

    public function testGetTaskStatus(): void
    {
        // Task status would need integration testing
        $this->markTestSkipped('Task status requires integration testing');
    }

    public function testGetTaskOutputSuccess(): void
    {
        // Task output would need integration testing
        $this->markTestSkipped('Task output requires integration testing');
    }

    public function testGetTaskOutputNotComplete(): void
    {
        // Task output would need integration testing
        $this->markTestSkipped('Task output requires integration testing');
    }

    // =================
    // Edge Cases
    // =================

    public function testProcessChatEmptyPrompt(): void
    {
        $this->textProcessingManager->method('hasProviders')
            ->willReturn(false);

        $this->expectException(\Exception::class);

        $this->service->processChat('');
    }

    public function testProcessChatSpecialCharacters(): void
    {
        // This would need integration testing with actual Task
        $this->markTestSkipped('Special characters require integration testing');
    }
}