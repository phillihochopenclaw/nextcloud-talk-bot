<?php
declare(strict_types=1);

namespace OCA\NextcloudTalkBot\Controller;

use OCA\NextcloudTalkBot\Service\SettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * Settings Controller for admin configuration
 */
class SettingsController extends Controller {
	private IConfig $config;
	private LoggerInterface $logger;
	private string $appName;
	private SettingsService $settingsService;

	public function __construct(
		string $appName,
		IRequest $request,
		IConfig $config,
		LoggerInterface $logger,
		SettingsService $settingsService
	) {
		parent::__construct($appName, $request);
		$this->appName = $appName;
		$this->config = $config;
		$this->logger = $logger;
		$this->settingsService = $settingsService;
	}

	/**
	 * Get admin settings
	 * 
	 * @AdminRequired
	 */
	public function getAdmin(): JSONResponse {
		$settings = [
			'webhook_url' => $this->config->getAppValue($this->appName, 'webhook_url', ''),
			'bot_token' => $this->config->getAppValue($this->appName, 'bot_token', ''),
			'enable_echo' => (bool) $this->config->getAppValue($this->appName, 'enable_echo', '1'),
			'enable_commands' => (bool) $this->config->getAppValue($this->appName, 'enable_commands', '1'),
			'response_prefix' => $this->config->getAppValue($this->appName, 'response_prefix', '🤖 '),
		];

		return new JSONResponse([
			'status' => 'success',
			'settings' => $settings
		]);
	}

	/**
	 * Set admin settings
	 * 
	 * @AdminRequired
	 */
	public function setAdmin(): JSONResponse {
		$params = $this->request->getParams();
		$updated = [];

		// General settings
		$generalKeys = ['bot_url', 'bot_token', 'enable_ai', 'ai_model', 'max_messages'];
		
		// OpenAI settings (US-007)
		$openaiKeys = ['openai_enabled', 'openai_api_key', 'openai_model'];
		
		// Custom Provider settings (US-008)
		$customKeys = ['custom_provider_enabled', 'custom_provider_endpoint', 'custom_provider_model', 'custom_provider_headers'];
		
		$allKeys = array_merge($generalKeys, $openaiKeys, $customKeys);

		foreach ($allKeys as $key) {
			if (isset($params[$key])) {
				$value = $params[$key];
				
				// Validation
				if ($key === 'bot_url' && !empty($value)) {
					if (!filter_var($value, FILTER_VALIDATE_URL)) {
						return new JSONResponse([
							'status' => 'error',
							'message' => 'Invalid webhook URL format'
						], Http::STATUS_BAD_REQUEST);
					}
				}
				
				if ($key === 'custom_provider_endpoint' && !empty($value)) {
					if (!filter_var($value, FILTER_VALIDATE_URL)) {
						return new JSONResponse([
							'status' => 'error',
							'message' => 'Invalid endpoint URL format'
						], Http::STATUS_BAD_REQUEST);
					}
				}

				$this->config->setAppValue($this->appName, $key, (string) $value);
				$updated[$key] = $key === 'bot_token' || $key === 'openai_api_key' ? '***' : $value;
			}
		}

		$this->logger->info('Admin settings updated', ['keys' => array_keys($updated)]);

		return new JSONResponse([
			'status' => 'success',
			'message' => 'Settings updated',
			'updated' => $updated
		]);
	}

	/**
	 * Test AI connection (US-008, US-010)
	 * 
	 * @AdminRequired
	 */
	public function testAIConnection(): JSONResponse {
		$params = $this->request->getParams();
		$provider = $params['provider'] ?? '';
		$config = $params['config'] ?? [];

		if (empty($provider)) {
			return new JSONResponse([
				'status' => 'error',
				'message' => 'Provider is required'
			], Http::STATUS_BAD_REQUEST);
		}

		$result = $this->settingsService->testAIConnection($provider, $config);

		// Update status based on result
		if ($result['success']) {
			$this->settingsService->updateAIStatus('active');
		} else {
			$this->settingsService->updateAIStatus('error', $result['message']);
		}

		return new JSONResponse([
			'status' => $result['success'] ? 'success' : 'error',
			'message' => $result['message']
		]);
	}

	/**
	 * Get AI status (US-010)
	 * 
	 * @AdminRequired
	 */
	public function getAIStatus(): JSONResponse {
		return new JSONResponse([
			'status' => 'success',
			'data' => [
				'activeProvider' => $this->config->getAppValue($this->appName, 'active_provider', 'none'),
				'aiStatus' => $this->config->getAppValue($this->appName, 'ai_status', 'inactive'),
				'lastError' => $this->config->getAppValue($this->appName, 'last_error', ''),
			]
		]);
	}

	/**
	 * Set active provider (US-007, US-008)
	 * 
	 * @AdminRequired
	 */
	public function setActiveProvider(): JSONResponse {
		$params = $this->request->getParams();
		$provider = $params['provider'] ?? '';

		$allowedProviders = ['openai', 'custom', 'none'];
		if (!in_array($provider, $allowedProviders, true)) {
			return new JSONResponse([
				'status' => 'error',
				'message' => 'Invalid provider'
			], Http::STATUS_BAD_REQUEST);
		}

		$this->config->setAppValue($this->appName, 'active_provider', $provider);
		
		if ($provider === 'none') {
			$this->settingsService->updateAIStatus('inactive');
		}

		return new JSONResponse([
			'status' => 'success',
			'message' => 'Active provider updated'
		]);
	}
}