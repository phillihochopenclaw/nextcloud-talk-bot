<?php
declare(strict_types=1);

namespace OCP;

/**
 * Mock interface for OCP\IConfig
 */
interface IConfig {
	public function getAppValue(string $appId, string $key, string $default = ''): string;
	public function setAppValue(string $appId, string $key, string $value): void;
	public function deleteAppValue(string $appId, string $key): void;
	public function getUserValue(string $userId, string $appId, string $key, string $default = ''): string;
	public function setUserValue(string $userId, string $appId, string $key, string $value): void;
}