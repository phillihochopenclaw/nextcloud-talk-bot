<?php
/**
 * Nextcloud Talk Bot - OpenAI Config Service
 * US-007: API Key Management for OpenAI
 */
declare(strict_types=1);

namespace OCA\NextcloudTalkBot\Service;

use OCP\IConfig;

class OpenAIConfigService {

	private const APP_ID = 'talk_bot';

	public function __construct(
		private IConfig $config
	) {}

	/**
	 * Get the OpenAI API key
	 *
	 * @return string
	 */
	public function getApiKey(): string {
		return $this->config->getAppValue(self::APP_ID, 'openai_api_key', '');
	}

	/**
	 * Check if OpenAI API key is configured
	 *
	 * @return bool
	 */
	public function hasApiKey(): bool {
		$apiKey = $this->getApiKey();
		return !empty($apiKey) && $apiKey !== '';
	}

	/**
	 * Set the OpenAI API key
	 *
	 * @param string $apiKey
	 * @return void
	 */
	public function setApiKey(string $apiKey): void {
		$this->config->setAppValue(self::APP_ID, 'openai_api_key', $apiKey);
	}

	/**
	 * Get the OpenAI organization ID (optional)
	 *
	 * @return string
	 */
	public function getOrganizationId(): string {
		return $this->config->getAppValue(self::APP_ID, 'openai_org_id', '');
	}

	/**
	 * Set the OpenAI organization ID
	 *
	 * @param string $orgId
	 * @return void
	 */
	public function setOrganizationId(string $orgId): void {
		$this->config->setAppValue(self::APP_ID, 'openai_org_id', $orgId);
	}

	/**
	 * Get the default model
	 *
	 * @return string
	 */
	public function getDefaultModel(): string {
		return $this->config->getAppValue(self::APP_ID, 'openai_model', 'gpt-4');
	}

	/**
	 * Set the default model
	 *
	 * @param string $model
	 * @return void
	 */
	public function setDefaultModel(string $model): void {
		$this->config->setAppValue(self::APP_ID, 'openai_model', $model);
	}

	/**
	 * Get maximum tokens for responses
	 *
	 * @return int
	 */
	public function getMaxTokens(): int {
		return (int) $this->config->getAppValue(self::APP_ID, 'openai_max_tokens', 1024);
	}

	/**
	 * Set maximum tokens for responses
	 *
	 * @param int $maxTokens
	 * @return void
	 */
	public function setMaxTokens(int $maxTokens): void {
		$this->config->setAppValue(self::APP_ID, 'openai_max_tokens', (string) $maxTokens);
	}

	/**
	 * Get temperature setting
	 *
	 * @return float
	 */
	public function getTemperature(): float {
		return (float) $this->config->getAppValue(self::APP_ID, 'openai_temperature', '0.7');
	}

	/**
	 * Set temperature setting
	 *
	 * @param float $temperature
	 * @return void
	 */
	public function setTemperature(float $temperature): void {
		$this->config->setAppValue(self::APP_ID, 'openai_temperature', (string) $temperature);
	}

	/**
	 * Get system prompt
	 *
	 * @return string
	 */
	public function getSystemPrompt(): string {
		return $this->config->getAppValue(self::APP_ID, 'openai_system_prompt', '');
	}

	/**
	 * Set system prompt
	 *
	 * @param string $prompt
	 * @return void
	 */
	public function setSystemPrompt(string $prompt): void {
		$this->config->setAppValue(self::APP_ID, 'openai_system_prompt', $prompt);
	}

	/**
	 * Get all OpenAI settings
	 *
	 * @return array
	 */
	public function getAllSettings(): array {
		return [
			'apiKey' => $this->hasApiKey() ? '***' . substr($this->getApiKey(), -4) : '',
			'organizationId' => $this->getOrganizationId(),
			'model' => $this->getDefaultModel(),
			'maxTokens' => $this->getMaxTokens(),
			'temperature' => $this->getTemperature(),
		];
	}

	/**
	 * Delete API key (for security)
	 *
	 * @return void
	 */
	public function deleteApiKey(): void {
		$this->config->deleteAppValue(self::APP_ID, 'openai_api_key');
	}
}
