<?php
/**
 * Nextcloud Talk Bot - AI Provider Detector Service
 * US-005: Erkennt verfügbare AI-Provider (Nextcloud AI, OpenAI, Custom)
 */
declare(strict_types=1);

namespace OCA\NextcloudTalkBot\Service;

use OCP\IConfig;

class AIProviderDetector {

	private const APP_ID = 'talk_bot';

	public const PROVIDER_NEXTCLOUD_AI = 'nextcloud_ai';
	public const PROVIDER_OPENAI = 'openai';
	public const PROVIDER_CUSTOM = 'custom';
	public const PROVIDER_NONE = 'none';

	public function __construct(
		private IConfig $config,
		private OpenAIConfigService $openAIConfig
	) {}

	/**
	 * Detect available AI provider
	 *
	 * @return string Provider identifier
	 */
	public function detectProvider(): string {
		// Priority 1: Nextcloud AI (built-in TextProcessing)
		if ($this->isNextcloudAIAvailable()) {
			return self::PROVIDER_NEXTCLOUD_AI;
		}

		// Priority 2: OpenAI (if API key configured)
		if ($this->isOpenAIConfigured()) {
			return self::PROVIDER_OPENAI;
		}

		// Priority 3: Custom Provider (if endpoint configured)
		if ($this->isCustomProviderConfigured()) {
			return self::PROVIDER_CUSTOM;
		}

		return self::PROVIDER_NONE;
	}

	/**
	 * Check if Nextcloud AI (TextProcessing) is available
	 *
	 * @return bool
	 */
	public function isNextcloudAIAvailable(): bool {
		// Check if TextProcessing app is enabled and has providers
		// This would typically check OCP\TextProcessing\IManager
		$aiEnabled = $this->config->getAppValue(self::APP_ID, 'enable_ai', 'no') === 'yes';
		
		if (!$aiEnabled) {
			return false;
		}

		$aiProvider = $this->config->getAppValue(self::APP_ID, 'ai_provider', '');
		
		// If explicitly set to nextcloud_ai, check for TextProcessing
		if ($aiProvider === self::PROVIDER_NEXTCLOUD_AI) {
			return class_exists('\OCP\TextProcessing\IManager');
		}

		// Auto-detect: check if TextProcessing is available
		return class_exists('\OCP\TextProcessing\IManager');
	}

	/**
	 * Check if OpenAI is configured with API key
	 *
	 * @return bool
	 */
	public function isOpenAIConfigured(): bool {
		$aiEnabled = $this->config->getAppValue(self::APP_ID, 'enable_ai', 'no') === 'yes';
		
		if (!$aiEnabled) {
			return false;
		}

		$aiProvider = $this->config->getAppValue(self::APP_ID, 'ai_provider', '');

		// If explicitly set to openai, check for API key
		if ($aiProvider === self::PROVIDER_OPENAI) {
			return $this->openAIConfig->hasApiKey();
		}

		// Auto-detect: check if OpenAI API key is available
		return $this->openAIConfig->hasApiKey();
	}

	/**
	 * Check if Custom Provider is configured
	 *
	 * @return bool
	 */
	public function isCustomProviderConfigured(): bool {
		$aiEnabled = $this->config->getAppValue(self::APP_ID, 'enable_ai', 'no') === 'yes';
		
		if (!$aiEnabled) {
			return false;
		}

		$aiProvider = $this->config->getAppValue(self::APP_ID, 'ai_provider', '');

		if ($aiProvider === self::PROVIDER_CUSTOM) {
			$endpoint = $this->config->getAppValue(self::APP_ID, 'custom_ai_endpoint', '');
			return !empty($endpoint);
		}

		// Auto-detect: check if custom endpoint is configured
		$endpoint = $this->config->getAppValue(self::APP_ID, 'custom_ai_endpoint', '');
		$apiKey = $this->config->getAppValue(self::APP_ID, 'custom_ai_api_key', '');

		return !empty($endpoint) || !empty($apiKey);
	}

	/**
	 * Get all available providers with details
	 *
	 * @return array
	 */
	public function getAvailableProviders(): array {
		$providers = [];

		if ($this->isNextcloudAIAvailable()) {
			$providers[] = [
				'id' => self::PROVIDER_NEXTCLOUD_AI,
				'name' => 'Nextcloud AI',
				'description' => 'Built-in Nextcloud Text Processing',
				'available' => true,
			];
		}

		if ($this->isOpenAIConfigured()) {
			$providers[] = [
				'id' => self::PROVIDER_OPENAI,
				'name' => 'OpenAI',
				'description' => 'OpenAI API (GPT models)',
				'available' => true,
			];
		}

		if ($this->isCustomProviderConfigured()) {
			$providers[] = [
				'id' => self::PROVIDER_CUSTOM,
				'name' => 'Custom Provider',
				'description' => 'Custom AI endpoint',
				'available' => true,
			];
		}

		return $providers;
	}

	/**
	 * Get current provider configuration
	 *
	 * @return array
	 */
	public function getCurrentProviderConfig(): array {
		return [
			'provider' => $this->detectProvider(),
			'model' => $this->config->getAppValue(self::APP_ID, 'ai_model', 'claude-3-opus'),
			'customEndpoint' => $this->config->getAppValue(self::APP_ID, 'custom_ai_endpoint', ''),
			'customModel' => $this->config->getAppValue(self::APP_ID, 'custom_ai_model', ''),
		];
	}
}
