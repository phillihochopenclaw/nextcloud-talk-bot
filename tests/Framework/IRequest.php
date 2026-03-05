<?php
declare(strict_types=1);

namespace OCP;

/**
 * Mock interface for OCP\IRequest
 */
interface IRequest {
	public function getParams(): array;
	public function getParam(string $key, $default = null);
	public function getMethod(): string;
	public function getHeader(string $key): string;
}