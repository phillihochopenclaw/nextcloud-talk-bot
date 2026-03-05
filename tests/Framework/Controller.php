<?php
declare(strict_types=1);

namespace OCP\AppFramework;

use OCP\IRequest;

/**
 * Mock implementation of OCP\AppFramework\Controller for testing
 */
abstract class Controller {
	protected string $appName;
	protected IRequest $request;

	public function __construct(string $appName, IRequest $request) {
		$this->appName = $appName;
		$this->request = $request;
	}

	/**
	 * Get app name
	 */
	public function getAppName(): string {
		return $this->appName;
	}

	/**
	 * Get request
	 */
	public function getRequest(): IRequest {
		return $this->request;
	}
}