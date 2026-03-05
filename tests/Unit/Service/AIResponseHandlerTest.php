<?php
/**
 * Unit tests for AIResponseHandler
 */
declare(strict_types=1);

namespace OCA\TalkBot\Tests\Unit\Service;

use OCA\TalkBot\Service\AIResponseHandler;
use OCA\TalkBot\Service\AIProviderDetector;
use OCA\TalkBot\Service\NextcloudAIService;
use OCA\TalkBot\Service\OpenAIService;
use OCA\TalkBot\Service\CustomAIService;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

class AIResponseHandlerTest extends TestCase {

	private AIProviderDetector $providerDetector;
	private NextcloudAIService $nextcloudAI;
	private OpenAIService $openAI;
	private CustomAIService $customAI;
	private LoggerInterface $logger;
	private AIResponseHandler $handler;

	protected function setUp(): void {
		parent::setUp();

		$this->providerDetector = $this->createMock(AIProviderDetector::class);
		$this->nextcloudAI = $this->createMock(NextcloudAIService::class);
		$this->openAI = $this->createMock(OpenAIService::class);
		$this->customAI = $this->createMock(CustomAIService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		
		$this->handler = new AIResponseHandler(
			$this->providerDetector,
			$this->nextcloudAI,
			$this->openAI,
			$this->customAI,
			$this->logger
		);
	}

	public function testProcessRequestReturnsSuccessResponse(): void {
		$this->providerDetector->expects($this->once())
			->method('detectProvider')
			->willReturn(AIProviderDetector::PROVIDER_OPENAI);

		$this->openAI->expects($this->once())
			->method('chat')
			->with('Hello', [])
			->willReturn('Hi there!');

		$response = $this->handler->processRequest('Hello');

		$this->assertTrue($response->success);
		$this->assertEquals('Hi there!', $response->content);
		$this->assertEquals(AIProviderDetector::PROVIDER_OPENAI, $response->provider);
		$this->assertNull($response->error);
	}

	public function testProcessRequestAddsToHistory(): void {
		$this->providerDetector->expects($this->once())
			->method('detectProvider')
			->willReturn(AIProviderDetector::PROVIDER_OPENAI);

		$this->openAI->expects($this->once())
			->method('chat')
			->willReturn('Response');

		$this->handler->processRequest('Hello');

		$history = $this->handler->getHistory();
		
		$this->assertCount(2, $history);
		$this->assertEquals('user', $history[0]['role']);
		$this->assertEquals('Hello', $history[0]['content']);
		$this->assertEquals('assistant', $history[1]['role']);
		$this->assertEquals('Response', $history[1]['content']);
	}

	public function testProcessRequestWithExplicitProvider(): void {
		$this->providerDetector->expects($this->never())
			->method('detectProvider');

		$this->nextcloudAI->expects($this->once())
			->method('processChat')
			->with('Hello')
			->willReturn('Nextcloud AI Response');

		$response = $this->handler->processRequest('Hello', [
			'provider' => AIProviderDetector::PROVIDER_NEXTCLOUD_AI
		]);

		$this->assertEquals('Nextcloud AI Response', $response->content);
	}

	public function testProcessRequestReturnsErrorOnException(): void {
		$this->providerDetector->expects($this->once())
			->method('detectProvider')
			->willReturn(AIProviderDetector::PROVIDER_OPENAI);

		$this->openAI->expects($this->once())
			->method('chat')
			->willThrowException(new \Exception('API Error'));

		$this->logger->expects($this->once())
			->method('error');

		$response = $this->handler->processRequest('Hello');

		$this->assertFalse($response->success);
		$this->assertEquals('API Error', $response->error);
		$this->assertEquals('', $response->content);
	}

	public function testProcessRequestTriesFallbackOnError(): void {
		$this->providerDetector->expects($this->once())
			->method('detectProvider')
			->willReturn(AIProviderDetector::PROVIDER_OPENAI);

		$this->openAI->expects($this->once())
			->method('chat')
			->willThrowException(new \Exception('API Error'));

		// Try Nextcloud AI as fallback
		$this->nextcloudAI->expects($this->once())
			->method('isAvailable')
			->willReturn(true);

		$this->nextcloudAI->expects($this->once())
			->method('processChat')
			->with('Hello')
			->willReturn('Fallback response');

		$response = $this->handler->processRequest('Hello');

		$this->assertTrue($response->success);
		$this->assertEquals('Fallback response', $response->content);
		$this->assertTrue($response->fallback);
	}

	public function testAddToHistoryStoresMessages(): void {
		$this->handler->addToHistory('user', 'Message 1');
		$this->handler->addToHistory('assistant', 'Message 2');

		$history = $this->handler->getHistory();

		$this->assertCount(2, $history);
		$this->assertEquals('user', $history[0]['role']);
		$this->assertEquals('Message 1', $history[0]['content']);
	}

	public function testClearHistoryRemovesAllMessages(): void {
		$this->handler->addToHistory('user', 'Message 1');
		$this->handler->addToHistory('assistant', 'Message 2');
		$this->handler->clearHistory();

		$history = $this->handler->getHistory();

		$this->assertEmpty($history);
	}

	public function testMaxHistoryLengthTruncatesHistory(): void {
		$this->handler->setMaxHistoryLength(3);

		$this->handler->addToHistory('user', 'Message 1');
		$this->handler->addToHistory('assistant', 'Message 2');
		$this->handler->addToHistory('user', 'Message 3');
		$this->handler->addToHistory('assistant', 'Message 4');
		$this->handler->addToHistory('user', 'Message 5');

		$history = $this->handler->getHistory();

		$this->assertCount(3, $history);
		$this->assertEquals('Message 3', $history[0]['content']);
	}

	public function testSetSystemPromptAddsAtBeginning(): void {
		$this->handler->addToHistory('user', 'Hello');
		$this->handler->setSystemPrompt('You are helpful.');

		$history = $this->handler->getHistory();

		$this->assertEquals('system', $history[0]['role']);
		$this->assertEquals('You are helpful.', $history[0]['content']);
	}

	public function testGetAvailableProvidersReturnsFromDetector(): void {
		$expectedProviders = [
			['id' => 'openai', 'name' => 'OpenAI'],
			['id' => 'custom', 'name' => 'Custom'],
		];

		$this->providerDetector->expects($this->once())
			->method('getAvailableProviders')
			->willReturn($expectedProviders);

		$providers = $this->handler->getAvailableProviders();

		$this->assertEquals($expectedProviders, $providers);
	}

	public function testDetectBestProviderReturnsFromDetector(): void {
		$this->providerDetector->expects($this->once())
			->method('detectProvider')
			->willReturn(AIProviderDetector::PROVIDER_OPENAI);

		$provider = $this->handler->detectBestProvider();

		$this->assertEquals(AIProviderDetector::PROVIDER_OPENAI, $provider);
	}

	public function testGetCurrentProviderReturnsProvider(): void {
		$this->providerDetector->expects($this->once())
			->method('detectProvider')
			->willReturn(AIProviderDetector::PROVIDER_OPENAI);

		$this->openAI->expects($this->once())
			->method('chat')
			->willReturn('Response');

		$this->handler->processRequest('Hello');

		$this->assertEquals(AIProviderDetector::PROVIDER_OPENAI, $this->handler->getCurrentProvider());
	}

	public function testResponseToArrayReturnsCorrectStructure(): void {
		$this->providerDetector->expects($this->once())
			->method('detectProvider')
			->willReturn(AIProviderDetector::PROVIDER_OPENAI);

		$this->openAI->expects($this->once())
			->method('chat')
			->willReturn('Response');

		$response = $this->handler->processRequest('Hello');
		$array = $response->toArray();

		$this->assertArrayHasKey('success', $array);
		$this->assertArrayHasKey('provider', $array);
		$this->assertArrayHasKey('content', $array);
		$this->assertArrayHasKey('history', $array);
		$this->assertArrayHasKey('error', $array);
		$this->assertArrayHasKey('fallback', $array);
	}

	public function testGetContentOrDefaultReturnsContentOnSuccess(): void {
		$this->providerDetector->expects($this->once())
			->method('detectProvider')
			->willReturn(AIProviderDetector::PROVIDER_OPENAI);

		$this->openAI->expects($this->once())
			->method('chat')
			->willReturn('Response');

		$response = $this->handler->processRequest('Hello');

		$this->assertEquals('Response', $response->getContentOrDefault('Default'));
	}

	public function testGetContentOrDefaultReturnsDefaultOnFailure(): void {
		$this->providerDetector->expects($this->once())
			->method('detectProvider')
			->willReturn(AIProviderDetector::PROVIDER_OPENAI);

		$this->openAI->expects($this->once())
			->method('chat')
			->willThrowException(new \Exception('Error'));

		$response = $this->handler->processRequest('Hello');

		$this->assertEquals('Default', $response->getContentOrDefault('Default'));
	}
}
