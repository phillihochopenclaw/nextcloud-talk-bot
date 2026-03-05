<?php
declare(strict_types=1);

namespace OCA\TalkBot\Tests\Framework;

/**
 * Mock implementation of OCP\IConfig for testing
 * 
 * This provides a simplified in-memory config storage for unit tests.
 */
class MockConfig {
	private array $appValues = [];
	private array $userValues = [];

	public function __construct(array $initialValues = []) {
		$this->appValues = $initialValues;
	}

	/**
	 * Get an app value
	 */
	public function getAppValue(string $appId, string $key, string $default = ''): string {
		return $this->appValues[$appId][$key] ?? $default;
	}

	/**
	 * Set an app value
	 */
	public function setAppValue(string $appId, string $key, string $value): void {
		if (!isset($this->appValues[$appId])) {
			$this->appValues[$appId] = [];
		}
		$this->appValues[$appId][$key] = $value;
	}

	/**
	 * Delete an app value
	 */
	public function deleteAppValue(string $appId, string $key): void {
		unset($this->appValues[$appId][$key]);
	}

	/**
	 * Get a user value
	 */
	public function getUserValue(string $userId, string $appId, string $key, string $default = ''): string {
		return $this->userValues[$userId][$appId][$key] ?? $default;
	}

	/**
	 * Set a user value
	 */
	public function setUserValue(string $userId, string $appId, string $key, string $value): void {
		if (!isset($this->userValues[$userId])) {
			$this->userValues[$userId] = [];
		}
		if (!isset($this->userValues[$userId][$appId])) {
			$this->userValues[$userId][$appId] = [];
		}
		$this->userValues[$userId][$appId][$key] = $value;
	}

	/**
	 * Get all stored app values (for testing)
	 */
	public function getAllAppValues(): array {
		return $this->appValues;
	}

	/**
	 * Get all stored user values (for testing)
	 */
	public function getAllUserValues(): array {
		return $this->userValues;
	}
}