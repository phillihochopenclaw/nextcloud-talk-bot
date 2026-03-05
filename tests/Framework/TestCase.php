<?php
declare(strict_types=1);

namespace OCA\TalkBot\Tests\Framework;

use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Log\LoggerInterface;

/**
 * Base Test Case for Nextcloud Talk Bot tests
 * 
 * Provides mock implementations of Nextcloud core interfaces
 */
abstract class TestCase extends BaseTestCase {
	
	protected function setUp(): void {
		parent::setUp();
	}

	protected function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Create a mock logger
	 */
	protected function createMockLogger(): LoggerInterface {
		return $this->createMock(LoggerInterface::class);
	}

	/**
	 * Create a mock request
	 */
	protected function createMockRequest(array $params = [], ?array $body = null): MockRequest {
		return new MockRequest($params, $body);
	}

	/**
	 * Create a mock config
	 */
	protected function createMockConfig(array $values = []): MockConfig {
		return new MockConfig($values);
	}
}