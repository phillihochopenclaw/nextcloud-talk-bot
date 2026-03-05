<?php
declare(strict_types=1);

namespace OCP\AppFramework\Http;

/**
 * Mock implementation of OCP\AppFramework\Http\JSONResponse for testing
 */
class JSONResponse {
	protected array $data;
	protected int $status;

	public function __construct(array $data = [], int $status = 200) {
		$this->data = $data;
		$this->status = $status;
	}

	/**
	 * Get response data
	 */
	public function getData(): array {
		return $this->data;
	}

	/**
	 * Get HTTP status code
	 */
	public function getStatus(): int {
		return $this->status;
	}

	/**
	 * Set response data
	 */
	public function setData(array $data): self {
		$this->data = $data;
		return $this;
	}

	/**
	 * Set HTTP status code
	 */
	public function setStatus(int $status): self {
		$this->status = $status;
		return $this;
	}
}