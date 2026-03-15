<?php
/**
 * Nextcloud Talk Bot - Custom AI Service
 * US-008: Generischer HTTP Client für Custom AI Provider
 */
declare(strict_types=1);

namespace OCA\NextcloudTalkBot\Service;

use OCP\Http\Client\IClient;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class CustomAIService {

	private const APP_ID = 'talk_bot';

	public function __construct(
		private IClient $httpClient,
		private IConfig $config,
		private LoggerInterface $logger
	) {}

	/**
	 * Check if custom provider is configured
	 *
	 * @return bool
	 */
	public function isConfigured(): bool {
		$endpoint = $this->getEndpoint();
		return !empty($endpoint);
	}

	/**
	 * Get the custom endpoint URL
	 *
	 * @return string
	 */
	public function getEndpoint(): string {
		return $this->config->getAppValue(self::APP_ID, 'custom_ai_endpoint', '');
	}

	/**
	 * Set the custom endpoint URL
	 *
	 * @param string $endpoint
	 * @return void
	 */
	public function setEndpoint(string $endpoint): void {
		$this->config->setAppValue(self::APP_ID, 'custom_ai_endpoint', $endpoint);
	}

	/**
	 * Get the custom API key
	 *
	 * @return string
	 */
	public function getApiKey(): string {
		return $this->config->getAppValue(self::APP_ID, 'custom_ai_api_key', '');
	}

	/**
	 * Set the custom API key
	 *
	 * @param string $apiKey
	 * @return void
	 */
	public function setApiKey(string $apiKey): void {
		$this->config->setAppValue(self::APP_ID, 'custom_ai_api_key', $apiKey);
	}

	/**
	 * Get the custom model name
	 *
	 * @return string
	 */
	public function getModel(): string {
		return $this->config->getAppValue(self::APP_ID, 'custom_ai_model', '');
	}

	/**
	 * Set the custom model name
	 *
	 * @param string $model
	 * @return void
	 */
	public function setModel(string $model): void {
		$this->config->setAppValue(self::APP_ID, 'custom_ai_model', $model);
	}

	/**
	 * Get custom headers
	 *
	 * @return array
	 */
	public function getHeaders(): array {
		$headersJson = $this->config->getAppValue(self::APP_ID, 'custom_ai_headers', '{}');
		$headers = json_decode($headersJson, true);
		return is_array($headers) ? $headers : [];
	}

	/**
	 * Set custom headers
	 *
	 * @param array $headers
	 * @return void
	 */
	public function setHeaders(array $headers): void {
		$this->config->setAppValue(self::APP_ID, 'custom_ai_headers', json_encode($headers));
	}

	/**
	 * Get request timeout
	 *
	 * @return int
	 */
	public function getTimeout(): int {
		return (int) $this->config->getAppValue(self::APP_ID, 'custom_ai_timeout', '30');
	}

	/**
	 * Set request timeout
	 *
	 * @param int $timeout
	 * @return void
	 */
	public function setTimeout(int $timeout): void {
		$this->config->setAppValue(self::APP_ID, 'custom_ai_timeout', (string) $timeout);
	}

	/**
	 * Process a chat request to the custom provider
	 *
	 * @param string $prompt User message
	 * @param array $messages Previous conversation messages
	 * @return string AI response
	 * @throws \Exception
	 */
	public function chat(string $prompt, array $messages = []): string {
		if (!$this->isConfigured()) {
			throw new \Exception('Custom AI provider not configured');
		}

		$endpoint = $this->getEndpoint();
		$apiKey = $this->getApiKey();
		$model = $this->getModel();
		$headers = $this->getHeaders();

		// Build request payload (OpenAI-compatible format by default)
		$payload = $this->buildPayload($prompt, $messages);

		// Build request headers
		$requestHeaders = $this->buildHeaders($apiKey, $headers);

		return $this->makeRequest($endpoint, $payload, $requestHeaders);
	}

	/**
	 * Build request payload
	 *
	 * @param string $prompt
	 * @param array $messages
	 * @return array
	 */
	private function buildPayload(string $prompt, array $messages): array {
		$model = $this->getModel();
		
		$payload = [
			'model' => $model ?: 'default',
			'messages' => [],
		];

		// Add conversation history
		foreach ($messages as $message) {
			$payload['messages'][] = [
				'role' => $message['role'] ?? 'user',
				'content' => $message['content'] ?? '',
			];
		}

		// Add current prompt
		$payload['messages'][] = [
			'role' => 'user',
			'content' => $prompt,
		];

		return $payload;
	}

	/**
	 * Build request headers
	 *
	 * @param string $apiKey
	 * @param array $customHeaders
	 * @return array
	 */
	private function buildHeaders(string $apiKey, array $customHeaders): array {
		$headers = [
			'Content-Type' => 'application/json',
		];

		if (!empty($apiKey)) {
			$authScheme = $this->config->getAppValue(self::APP_ID, 'custom_ai_auth_scheme', 'Bearer');
			$headers['Authorization'] = $authScheme . ' ' . $apiKey;
		}

		// Merge custom headers
		return array_merge($headers, $customHeaders);
	}

	/**
	 * Make HTTP request to custom provider
	 *
	 * @param string $url
	 * @param array $payload
	 * @param array $headers
	 * @return string
	 * @throws \Exception
	 */
	private function makeRequest(string $url, array $payload, array $headers): string {
		$timeout = $this->getTimeout();
		$attempt = 0;
		$maxRetries = 3;

		while ($attempt < $maxRetries) {
			$attempt++;

			try {
				$response = $this->httpClient->post(
					$url,
					[
						'headers' => $headers,
						'body' => json_encode($payload),
						'timeout' => $timeout,
					]
				);

				$statusCode = $response->getStatusCode();
				$body = $response->getBody();

				if ($statusCode === 200) {
					return $this->parseResponse($body);
				}

				if ($statusCode === 429 || ($statusCode >= 500 && $statusCode < 600)) {
					$waitTime = pow(2, $attempt);
					$this->logger->warning("Custom AI rate limited, waiting {$waitTime}s before retry");
					usleep($waitTime * 1000000);
					continue;
				}

				throw new \Exception("Custom AI API error ({$statusCode}): {$body}");

			} catch (\Exception $e) {
				$this->logger->error("Custom AI request failed (attempt {$attempt}): " . $e->getMessage());

				if ($attempt >= $maxRetries) {
					throw new \Exception('Custom AI request failed: ' . $e->getMessage());
				}

				$waitTime = pow(2, $attempt);
				usleep($waitTime * 1000000);
			}
		}

		throw new \Exception('Custom AI request failed after maximum retries');
	}

	/**
	 * Parse API response
	 *
	 * @param string $body Response body
	 * @return string
	 * @throws \Exception
	 */
	private function parseResponse(string $body): string {
		$data = json_decode($body, true);

		if (json_last_error() !== JSON_ERROR_NONE) {
			// Return raw body if not JSON
			return $body;
		}

		// Try OpenAI-compatible response format
		if (isset($data['choices'][0]['message']['content'])) {
			return $data['choices'][0]['message']['content'];
		}

		// Try custom response format
		if (isset($data['response'])) {
			return $data['response'];
		}

		if (isset($data['text'])) {
			return $data['text'];
		}

		if (isset($data['content'])) {
			return $data['content'];
		}

		// Return JSON string if no recognized format
		return json_encode($data, JSON_PRETTY_PRINT);
	}

	/**
	 * Test connection to custom provider
	 *
	 * @return bool
	 */
	public function testConnection(): bool {
		try {
			$endpoint = $this->getEndpoint();
			
			if (empty($endpoint)) {
				return false;
			}

			// Try a simple health check request
			$headers = $this->buildHeaders($this->getApiKey(), $this->getHeaders());
			
			// Send a minimal request to test connectivity
			$response = $this->httpClient->post(
				$endpoint,
				[
					'headers' => $headers,
					'body' => json_encode([
						'model' => $this->getModel() ?: 'default',
						'messages' => [['role' => 'user', 'content' => 'ping']],
					]),
					'timeout' => 10,
				]
			);

			return in_array($response->getStatusCode(), [200, 400, 422]);
		} catch (\Exception $e) {
			$this->logger->error('Custom AI connection test failed: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Get all custom provider settings
	 *
	 * @return array
	 */
	public function getSettings(): array {
		return [
			'endpoint' => $this->getEndpoint(),
			'model' => $this->getModel(),
			'hasApiKey' => !empty($this->getApiKey()),
			'headers' => $this->getHeaders(),
			'timeout' => $this->getTimeout(),
		];
	}
}
