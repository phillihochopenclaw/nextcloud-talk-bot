<?php
declare(strict_types=1);

namespace OCA\TalkBot\Tests\Framework;

/**
 * Mock implementation of OCP\IRequest for testing
 * 
 * This provides a simple request object for unit tests.
 */
class MockRequest {
	private array $params;
	private ?array $body;
	private string $method;
	private array $headers;

	public function __construct(array $params = [], ?array $body = null, string $method = 'GET') {
		$this->params = $params;
		$this->body = $body;
		$this->method = $method;
		$this->headers = [];
	}

	/**
	 * Get all request parameters
	 */
	public function getParams(): array {
		$result = $this->params;
		if ($this->body !== null) {
			$result['body'] = json_encode($this->body);
		}
		return $result;
	}

	/**
	 * Get a specific parameter
	 */
	public function getParam(string $key, $default = null) {
		return $this->params[$key] ?? $default;
	}

	/**
	 * Set a parameter
	 */
	public function setParam(string $key, $value): self {
		$this->params[$key] = $value;
		return $this;
	}

	/**
	 * Get request method
	 */
	public function getMethod(): string {
		return $this->method;
	}

	/**
	 * Set request method
	 */
	public function setMethod(string $method): self {
		$this->method = $method;
		return $this;
	}

	/**
	 * Get a header
	 */
	public function getHeader(string $key): string {
		return $this->headers[$key] ?? '';
	}

	/**
	 * Set a header
	 */
	public function setHeader(string $key, string $value): self {
		$this->headers[$key] = $value;
		return $this;
	}

	/**
	 * Get raw body
	 */
	public function getBody(): string {
		return $this->body !== null ? json_encode($this->body) : '';
	}

	/**
	 * Set body
	 */
	public function setBody(array $body): self {
		$this->body = $body;
		return $this;
	}
}