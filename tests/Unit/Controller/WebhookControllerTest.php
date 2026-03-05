<?php
declare(strict_types=1);

namespace OCA\TalkBot\Tests\Unit\Controller;

use OCA\TalkBot\Controller\WebhookController;
use OCA\TalkBot\Service\MessageService;
use OCA\TalkBot\Tests\Framework\TestCase;
use OCA\TalkBot\Tests\Framework\MockRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for WebhookController
 */
class WebhookControllerTest extends TestCase {
	private WebhookController $controller;
	private MessageService&MockObject $messageService;
	private LoggerInterface&MockObject $logger;
	private string $appName = 'talk_bot';

	protected function setUp(): void {
		parent::setUp();
		$this->messageService = $this->createMock(MessageService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	/**
	 * Create controller with request
	 */
	private function createController(MockRequest $request): WebhookController {
		return new WebhookController(
			$this->appName,
			$request,
			$this->messageService,
			$this->logger
		);
	}

	/**
	 * Test health check endpoint
	 */
	public function testHealthCheck(): void {
		$request = new MockRequest();
		$controller = $this->createController($request);

		$response = $controller->health();
		$data = json_decode(json_encode($response->getData()), true);

		$this->assertEquals('ok', $data['status']);
		$this->assertArrayHasKey('timestamp', $data);
		$this->assertEquals('1.0.0', $data['version']);
	}

	/**
	 * Test successful webhook receive
	 */
	public function testReceiveValidPayload(): void {
		$payload = [
			'message' => 'Hello Bot!',
			'user' => 'testuser',
			'conversation' => 'conv-123',
			'timestamp' => time()
		];

		$request = new MockRequest();
		$request->setBody($payload);
		$request->setParam('body', json_encode($payload));

		$this->messageService->expects($this->once())
			->method('processMessage')
			->with(
				$this->equalTo('Hello Bot!'),
				$this->equalTo('testuser'),
				$this->equalTo('conv-123'),
				$this->anything()
			)
			->willReturn([
				'action' => 'reply',
				'message' => '🤖 Echo: Hello Bot!',
				'metadata' => []
			]);

		$controller = $this->createController($request);
		$response = $controller->receive();
		$data = json_decode(json_encode($response->getData()), true);

		$this->assertEquals('success', $data['status']);
		$this->assertArrayHasKey('result', $data);
		$this->assertEquals('reply', $data['result']['action']);
	}

	/**
	 * Test webhook with missing message field
	 */
	public function testReceiveMissingMessage(): void {
		$payload = [
			'user' => 'testuser'
		];

		$request = new MockRequest();
		$request->setParam('body', json_encode($payload));

		$this->messageService->expects($this->never())
			->method('processMessage');

		$controller = $this->createController($request);
		$response = $controller->receive();
		$data = json_decode(json_encode($response->getData()), true);

		$this->assertEquals('error', $data['status']);
		$this->assertStringContainsString('Missing required fields', $data['message']);
	}

	/**
	 * Test webhook with missing user field
	 */
	public function testReceiveMissingUser(): void {
		$payload = [
			'message' => 'Hello'
		];

		$request = new MockRequest();
		$request->setParam('body', json_encode($payload));

		$this->messageService->expects($this->never())
			->method('processMessage');

		$controller = $this->createController($request);
		$response = $controller->receive();
		$data = json_decode(json_encode($response->getData()), true);

		$this->assertEquals('error', $data['status']);
		$this->assertStringContainsString('Missing required fields', $data['message']);
	}

	/**
	 * Test webhook with invalid JSON
	 */
	public function testReceiveInvalidJson(): void {
		$request = new MockRequest();
		$request->setParam('body', 'not valid json');

		$this->messageService->expects($this->never())
			->method('processMessage');

		$controller = $this->createController($request);
		$response = $controller->receive();
		$data = json_decode(json_encode($response->getData()), true);

		$this->assertEquals('error', $data['status']);
		$this->assertStringContainsString('Invalid JSON', $data['message']);
	}

	/**
	 * Test webhook with empty body
	 */
	public function testReceiveEmptyBody(): void {
		$request = new MockRequest();

		$this->messageService->expects($this->never())
			->method('processMessage');

		$controller = $this->createController($request);
		$response = $controller->receive();
		$data = json_decode(json_encode($response->getData()), true);

		$this->assertEquals('error', $data['status']);
		$this->assertStringContainsString('Invalid JSON', $data['message']);
	}

	/**
	 * Test webhook with optional fields omitted
	 */
	public function testReceiveOptionalFieldsOmitted(): void {
		$payload = [
			'message' => 'Hello',
			'user' => 'testuser'
		];

		$request = new MockRequest();
		$request->setParam('body', json_encode($payload));

		$this->messageService->expects($this->once())
			->method('processMessage')
			->with(
				$this->equalTo('Hello'),
				$this->equalTo('testuser'),
				$this->isNull(),
				$this->anything()
			)
			->willReturn(['action' => 'reply', 'message' => 'OK']);

		$controller = $this->createController($request);
		$response = $controller->receive();
		$data = json_decode(json_encode($response->getData()), true);

		$this->assertEquals('success', $data['status']);
	}

	/**
	 * Test webhook with service exception
	 */
	public function testReceiveServiceException(): void {
		$payload = [
			'message' => 'Hello',
			'user' => 'testuser'
		];

		$request = new MockRequest();
		$request->setParam('body', json_encode($payload));

		$this->messageService->expects($this->once())
			->method('processMessage')
			->willThrowException(new \RuntimeException('Service error'));

		$controller = $this->createController($request);
		$response = $controller->receive();
		$data = json_decode(json_encode($response->getData()), true);

		$this->assertEquals('error', $data['status']);
		$this->assertStringContainsString('Internal server error', $data['message']);
	}

	/**
	 * Test webhook handles command messages
	 */
	public function testReceiveCommandMessage(): void {
		$payload = [
			'message' => '/help',
			'user' => 'testuser'
		];

		$request = new MockRequest();
		$request->setParam('body', json_encode($payload));

		$this->messageService->expects($this->once())
			->method('processMessage')
			->with(
				$this->equalTo('/help'),
				$this->equalTo('testuser'),
				$this->isNull(),
				$this->anything()
			)
			->willReturn([
				'action' => 'reply',
				'message' => '🤖 Available commands...',
			]);

		$controller = $this->createController($request);
		$response = $controller->receive();
		$data = json_decode(json_encode($response->getData()), true);

		$this->assertEquals('success', $data['status']);
		$this->assertStringContainsString('Available commands', $data['result']['message']);
	}

	/**
	 * Test webhook with special characters in payload
	 */
	public function testReceiveSpecialCharacters(): void {
		$payload = [
			'message' => 'Hello "World" <script>alert(1)</script>',
			'user' => 'user@example.com'
		];

		$request = new MockRequest();
		$request->setParam('body', json_encode($payload));

		$this->messageService->expects($this->once())
			->method('processMessage')
			->willReturn(['action' => 'reply', 'message' => 'OK']);

		$controller = $this->createController($request);
		$response = $controller->receive();
		$data = json_decode(json_encode($response->getData()), true);

		$this->assertEquals('success', $data['status']);
	}

	/**
	 * Test webhook with unicode payload
	 */
	public function testReceiveUnicodePayload(): void {
		$payload = [
			'message' => 'Hello 🌍 你好 мир',
			'user' => '测试用户'
		];

		$request = new MockRequest();
		$request->setParam('body', json_encode($payload));

		$this->messageService->expects($this->once())
			->method('processMessage')
			->willReturn(['action' => 'reply', 'message' => 'OK']);

		$controller = $this->createController($request);
		$response = $controller->receive();
		$data = json_decode(json_encode($response->getData()), true);

		$this->assertEquals('success', $data['status']);
	}

	/**
	 * Test response headers for CORS
	 */
	public function testHealthCheckResponseFormat(): void {
		$request = new MockRequest();
		$controller = $this->createController($request);

		$response = $controller->health();

		$this->assertInstanceOf(\OCP\AppFramework\Http\JSONResponse::class, $response);
	}
}