<?php
declare(strict_types=1);

namespace OCA\TalkBot\Tests\Unit\Service;

use OCA\TalkBot\Service\MessageService;
use OCA\TalkBot\Tests\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for MessageService
 */
class MessageServiceTest extends TestCase {
	private MessageService $service;
	private LoggerInterface&MockObject $logger;

	protected function setUp(): void {
		parent::setUp();
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->service = new MessageService($this->logger);
	}

	/**
	 * Test basic message processing with echo enabled
	 */
	public function testProcessMessageEcho(): void {
		$result = $this->service->processMessage(
			'Hello Bot!',
			'testuser',
			'conversation-123',
			time()
		);

		$this->assertArrayHasKey('action', $result);
		$this->assertArrayHasKey('message', $result);
		$this->assertEquals('reply', $result['action']);
		$this->assertStringContainsString('Echo: Hello Bot!', $result['message']);
		$this->assertStringContainsString('🤖', $result['message']);
	}

	/**
	 * Test message processing with custom config
	 */
	public function testProcessMessageWithCustomConfig(): void {
		$service = new MessageService($this->logger, [
			'response_prefix' => '🤖🤖 ',
			'enable_echo' => true,
		]);

		$result = $service->processMessage('Test', 'user1');

		$this->assertStringContainsString('🤖🤖', $result['message']);
	}

	/**
	 * Test /help command
	 */
	public function testHelpCommand(): void {
		$result = $this->service->processMessage('/help', 'testuser');

		$this->assertEquals('reply', $result['action']);
		$this->assertStringContainsString('Available commands', $result['message']);
		$this->assertStringContainsString('/help', $result['message']);
		$this->assertStringContainsString('/status', $result['message']);
		$this->assertStringContainsString('/ping', $result['message']);
	}

	/**
	 * Test /status command
	 */
	public function testStatusCommand(): void {
		$result = $this->service->processMessage('/status', 'testuser');

		$this->assertEquals('reply', $result['action']);
		$this->assertStringContainsString('running normally', $result['message']);
		$this->assertStringContainsString('Version: 1.0.0', $result['message']);
	}

	/**
	 * Test /ping command
	 */
	public function testPingCommand(): void {
		$result = $this->service->processMessage('/ping', 'testuser');

		$this->assertEquals('reply', $result['action']);
		$this->assertStringContainsString('Pong!', $result['message']);
		$this->assertStringContainsString('🏓', $result['message']);
	}

	/**
	 * Test unknown command
	 */
	public function testUnknownCommand(): void {
		$result = $this->service->processMessage('/unknown', 'testuser');

		$this->assertEquals('reply', $result['action']);
		$this->assertStringContainsString('Unknown command', $result['message']);
		$this->assertStringContainsString('/help', $result['message']);
	}

	/**
	 * Test command case insensitivity
	 */
	public function testCommandCaseInsensitive(): void {
		$result = $this->service->processMessage('/PING', 'testuser');
		$this->assertStringContainsString('Pong!', $result['message']);

		$result = $this->service->processMessage('/Help', 'testuser');
		$this->assertStringContainsString('Available commands', $result['message']);
	}

	/**
	 * Test echo disabled
	 */
	public function testEchoDisabled(): void {
		$service = new MessageService($this->logger, [
			'enable_echo' => false,
		]);

		$result = $service->processMessage('Hello', 'testuser');

		$this->assertEquals('acknowledge', $result['action']);
		$this->assertEquals('Message received', $result['message']);
	}

	/**
	 * Test commands disabled
	 */
	public function testCommandsDisabled(): void {
		$service = new MessageService($this->logger, [
			'enable_commands' => false,
			'enable_echo' => true,
		]);

		// Command should be treated as regular message
		$result = $service->processMessage('/help', 'testuser');
		$this->assertStringContainsString('Echo: /help', $result['message']);
	}

	/**
	 * Test message metadata
	 */
	public function testMessageMetadata(): void {
		$timestamp = 1709500000;
		$result = $this->service->processMessage(
			'Test message',
			'john.doe',
			'conv-456',
			$timestamp
		);

		$this->assertArrayHasKey('metadata', $result);
		$this->assertArrayHasKey('original_user', $result['metadata']);
		$this->assertEquals('john.doe', $result['metadata']['original_user']);
		$this->assertArrayHasKey('processed_at', $result['metadata']);
	}

	/**
	 * Test setConfig and getConfig
	 */
	public function testConfigManagement(): void {
		$newConfig = [
			'response_prefix' => '🤖🤖🤖 ',
			'enable_echo' => false,
		];

		$this->service->setConfig($newConfig);
		$config = $this->service->getConfig();

		$this->assertEquals('🤖🤖🤖 ', $config['response_prefix']);
		$this->assertFalse($config['enable_echo']);
		// Default should still be present
		$this->assertTrue($config['enable_commands']);
	}

	/**
	 * Test empty message handling
	 */
	public function testEmptyMessage(): void {
		$result = $this->service->processMessage('', 'testuser');

		$this->assertEquals('reply', $result['action']);
		$this->assertStringContainsString('Echo:', $result['message']);
	}

	/**
	 * Test special characters in message
	 */
	public function testSpecialCharacters(): void {
		$specialMessage = 'Hello <script>alert("xss")</script> & "quotes" \'apostrophe\'';
		$result = $this->service->processMessage($specialMessage, 'testuser');

		$this->assertEquals('reply', $result['action']);
		$this->assertStringContainsString($specialMessage, $result['message']);
	}

	/**
	 * Test Unicode/Emoji in message
	 */
	public function testUnicodeMessage(): void {
		$unicodeMessage = 'Hello 🌍 World! 你好 мир';
		$result = $this->service->processMessage($unicodeMessage, 'testuser');

		$this->assertEquals('reply', $result['action']);
		$this->assertStringContainsString('🌍', $result['message']);
		$this->assertStringContainsString('你好', $result['message']);
	}

	/**
	 * Test very long message
	 */
	public function testLongMessage(): void {
		$longMessage = str_repeat('a', 10000);
		$result = $this->service->processMessage($longMessage, 'testuser');

		$this->assertEquals('reply', $result['action']);
		$this->assertArrayHasKey('message', $result);
	}

	/**
	 * Test null conversation handling
	 */
	public function testNullConversation(): void {
		$result = $this->service->processMessage(
			'Hello',
			'testuser',
			null,
			time()
		);

		$this->assertEquals('reply', $result['action']);
		$this->assertArrayHasKey('metadata', $result);
	}

	/**
	 * Test null timestamp handling
	 */
	public function testNullTimestamp(): void {
		$result = $this->service->processMessage(
			'Hello',
			'testuser',
			'conversation-123',
			null
		);

		$this->assertEquals('reply', $result['action']);
		$this->assertArrayHasKey('metadata', $result);
	}
}