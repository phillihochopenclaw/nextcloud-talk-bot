<?php
declare(strict_types=1);

namespace OCA\TalkBot\Tests\Unit\Controller;

use OCA\TalkBot\Controller\SettingsController;
use OCA\TalkBot\Tests\Framework\TestCase;
use OCA\TalkBot\Tests\Framework\MockRequest;
use OCA\TalkBot\Tests\Framework\MockConfig;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for SettingsController
 */
class SettingsControllerTest extends TestCase {
	private SettingsController $controller;
	private MockConfig $config;
	private LoggerInterface&MockObject $logger;
	private string $appName = 'talk_bot';

	protected function setUp(): void {
		parent::setUp();
		$this->config = new MockConfig();
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	/**
	 * Create controller with request
	 */
	private function createController(MockRequest $request): SettingsController {
		return new SettingsController(
			$this->appName,
			$request,
			$this->config,
			$this->logger
		);
	}

	/**
	 * Test getting admin settings with defaults
	 */
	public function testGetAdminSettingsDefaults(): void {
		$request = new MockRequest();
		$controller = $this->createController($request);

		$response = $controller->getAdmin();
		$data = json_decode(json_encode($response->getData()), true);

		$this->assertEquals('success', $data['status']);
		$this->assertArrayHasKey('settings', $data);
		
		$settings = $data['settings'];
		$this->assertEquals('', $settings['webhook_url']);
		$this->assertEquals('', $settings['bot_token']);
		$this->assertTrue($settings['enable_echo']);
		$this->assertTrue($settings['enable_commands']);
		$this->assertEquals('🤖 ', $settings['response_prefix']);
	}

	/**
	 * Test getting admin settings with stored values
	 */
	public function testGetAdminSettingsStored(): void {
		$this->config->setAppValue($this->appName, 'webhook_url', 'https://example.com/webhook');
		$this->config->setAppValue($this->appName, 'enable_echo', '0');
		$this->config->setAppValue($this->appName, 'response_prefix', '🤖🤖 ');

		$request = new MockRequest();
		$controller = $this->createController($request);

		$response = $controller->getAdmin();
		$data = json_decode(json_encode($response->getData()), true);

		$this->assertEquals('success', $data['status']);
		
		$settings = $data['settings'];
		$this->assertEquals('https://example.com/webhook', $settings['webhook_url']);
		$this->assertFalse($settings['enable_echo']);
		$this->assertEquals('🤖🤖 ', $settings['response_prefix']);
	}

	/**
	 * Test setting admin settings
	 */
	public function testSetAdminSettings(): void {
		$request = new MockRequest();
		$request->setParam('webhook_url', 'https://example.com/new-webhook');
		$request->setParam('enable_echo', '1');
		$request->setParam('enable_commands', '1');

		$controller = $this->createController($request);
		$response = $controller->setAdmin();
		$data = json_decode(json_encode($response->getData()), true);

		$this->assertEquals('success', $data['status']);
		$this->assertEquals('Settings updated', $data['message']);
		$this->assertArrayHasKey('updated', $data);
		
		// Verify settings were stored
		$this->assertEquals(
			'https://example.com/new-webhook',
			$this->config->getAppValue($this->appName, 'webhook_url', '')
		);
		$this->assertEquals('1', $this->config->getAppValue($this->appName, 'enable_echo', ''));
		$this->assertEquals('1', $this->config->getAppValue($this->appName, 'enable_commands', ''));
	}

	/**
	 * Test setting webhook_url with invalid URL
	 */
	public function testSetAdminInvalidWebhookUrl(): void {
		$request = new MockRequest();
		$request->setParam('webhook_url', 'not-a-valid-url');

		$controller = $this->createController($request);
		$response = $controller->setAdmin();
		$data = json_decode(json_encode($response->getData()), true);

		$this->assertEquals('error', $data['status']);
		$this->assertStringContainsString('Invalid webhook URL', $data['message']);
	}

	/**
	 * Test setting webhook_url with valid URLs
	 */
	public function testSetAdminValidWebhookUrls(): void {
		$validUrls = [
			'https://example.com/webhook',
			'https://subdomain.example.com/path/to/webhook?token=abc123',
			'http://localhost:8080/webhook',
		];

		foreach ($validUrls as $url) {
			$request = new MockRequest();
			$request->setParam('webhook_url', $url);

			$controller = $this->createController($request);
			$response = $controller->setAdmin();
			$data = json_decode(json_encode($response->getData()), true);

			$this->assertEquals('success', $data['status'], "URL $url should be valid");
		}
	}

	/**
	 * Test setting bot_token (should be masked in response)
	 */
	public function testSetAdminBotToken(): void {
		$request = new MockRequest();
		$request->setParam('bot_token', 'super-secret-token-12345');

		$controller = $this->createController($request);
		$response = $controller->setAdmin();
		$data = json_decode(json_encode($response->getData()), true);

		$this->assertEquals('success', $data['status']);
		
		// Token should be masked in response
		$this->assertEquals('***', $data['updated']['bot_token']);
		
		// But stored correctly
		$this->assertEquals(
			'super-secret-token-12345',
			$this->config->getAppValue($this->appName, 'bot_token', '')
		);
	}

	/**
	 * Test setting response_prefix
	 */
	public function testSetAdminResponsePrefix(): void {
		$request = new MockRequest();
		$request->setParam('response_prefix', '🤖🤖🤖 ');

		$controller = $this->createController($request);
		$response = $controller->setAdmin();
		$data = json_decode(json_encode($response->getData()), true);

		$this->assertEquals('success', $data['status']);
		$this->assertEquals('🤖🤖🤖 ', $data['updated']['response_prefix']);
	}

	/**
	 * Test setting multiple values at once
	 */
	public function testSetAdminMultipleSettings(): void {
		$request = new MockRequest();
		$request->setParam('webhook_url', 'https://example.com/webhook');
		$request->setParam('bot_token', 'token-xyz');
		$request->setParam('enable_echo', '0');
		$request->setParam('enable_commands', '1');
		$request->setParam('response_prefix', '⚡ ');

		$controller = $this->createController($request);
		$response = $controller->setAdmin();
		$data = json_decode(json_encode($response->getData()), true);

		$this->assertEquals('success', $data['status']);
		$this->assertCount(5, $data['updated']);
		
		// Verify all settings were stored
		$this->assertEquals('https://example.com/webhook', $this->config->getAppValue($this->appName, 'webhook_url', ''));
		$this->assertEquals('token-xyz', $this->config->getAppValue($this->appName, 'bot_token', ''));
		$this->assertEquals('0', $this->config->getAppValue($this->appName, 'enable_echo', ''));
		$this->assertEquals('1', $this->config->getAppValue($this->appName, 'enable_commands', ''));
		$this->assertEquals('⚡ ', $this->config->getAppValue($this->appName, 'response_prefix', ''));
	}

	/**
	 * Test that unknown settings are ignored
	 */
	public function testSetAdminIgnoresUnknownSettings(): void {
		$request = new MockRequest();
		$request->setParam('webhook_url', 'https://example.com/webhook');
		$request->setParam('unknown_setting', 'should-be-ignored');
		$request->setParam('malicious_key', 'hacker-attempt');

		$controller = $this->createController($request);
		$response = $controller->setAdmin();
		$data = json_decode(json_encode($response->getData()), true);

		$this->assertEquals('success', $data['status']);
		$this->assertArrayNotHasKey('unknown_setting', $data['updated']);
		$this->assertArrayNotHasKey('malicious_key', $data['updated']);
	}

	/**
	 * Test empty webhook_url is allowed
	 */
	public function testSetAdminEmptyWebhookUrl(): void {
		$request = new MockRequest();
		$request->setParam('webhook_url', '');

		$controller = $this->createController($request);
		$response = $controller->setAdmin();
		$data = json_decode(json_encode($response->getData()), true);

		$this->assertEquals('success', $data['status']);
		$this->assertEquals('', $this->config->getAppValue($this->appName, 'webhook_url', 'not-empty'));
	}

	/**
	 * Test setting boolean values as strings
	 */
	public function testSetAdminBooleanValues(): void {
		// Test with "1" (true)
		$request = new MockRequest();
		$request->setParam('enable_echo', '1');
		$controller = $this->createController($request);
		$response = $controller->setAdmin();
		$this->assertEquals('success', json_decode(json_encode($response->getData()), true)['status']);
		$this->assertEquals('1', $this->config->getAppValue($this->appName, 'enable_echo', ''));

		// Test with "0" (false)
		$request = new MockRequest();
		$request->setParam('enable_echo', '0');
		$controller = $this->createController($request);
		$response = $controller->setAdmin();
		$this->assertEquals('success', json_decode(json_encode($response->getData()), true)['status']);
		$this->assertEquals('0', $this->config->getAppValue($this->appName, 'enable_echo', ''));
	}

	/**
	 * Test getting settings after update
	 */
	public function testGetAfterSet(): void {
		// First set some values
		$request = new MockRequest();
		$request->setParam('webhook_url', 'https://test.example.com/hook');
		$request->setParam('enable_echo', '0');
		$controller = $this->createController($request);
		$controller->setAdmin();

		// Then get them
		$request = new MockRequest();
		$controller = $this->createController($request);
		$response = $controller->getAdmin();
		$data = json_decode(json_encode($response->getData()), true);

		$this->assertEquals('success', $data['status']);
		$this->assertEquals('https://test.example.com/hook', $data['settings']['webhook_url']);
		$this->assertFalse($data['settings']['enable_echo']);
	}

	/**
	 * Test response format consistency
	 */
	public function testResponseFormat(): void {
		$request = new MockRequest();
		$controller = $this->createController($request);

		$getResponse = $controller->getAdmin();
		$setData = json_decode(json_encode($getResponse->getData()), true);

		$this->assertArrayHasKey('status', $setData);
		$this->assertArrayHasKey('settings', $setData);

		$request = new MockRequest();
		$request->setParam('webhook_url', 'https://example.com');
		$controller = $this->createController($request);
		$setResponse = $controller->setAdmin();
		$setData = json_decode(json_encode($setResponse->getData()), true);

		$this->assertArrayHasKey('status', $setData);
		$this->assertArrayHasKey('message', $setData);
		$this->assertArrayHasKey('updated', $setData);
	}
}