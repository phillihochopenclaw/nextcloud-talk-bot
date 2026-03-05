<?php
/**
 * Nextcloud Talk Bot - OpenAI Service
 * US-007: HTTP Client für OpenAI API
 */
declare(strict_types=1);

namespace OCA\TalkBot\Service;

use OCP\Http\Client\IClient;
use OCP\Http\Client\IResponse;
use Psr\Log\LoggerInterface;

class OpenAIService {

	private const API_BASE_URL = 'https://api.openai.com/v1';
	private const MAX_RETRIES = 3;
	private const TIMEOUT = 30;

	public function __construct(
		private IClient $httpClient,
		private OpenAIConfigService $config,
		private LoggerInterface $logger
	) {}

	/**
	 * Send a chat completion request
	 *
	 * @param string $prompt User message
	 * @param array $messages Previous conversation messages
	 * @return string AI response
	 * @throws \Exception
	 */
	public function chat(string $prompt, array $messages = []): string {
		$apiKey = $this->config->getApiKey();
		
		if (empty($apiKey)) {
			throw new \Exception('OpenAI API key not configured');
		}

		// Build messages array with conversation history
		$requestMessages = $this->buildMessages($prompt, $messages);

		$payload = [
			'model' => $this->config->getDefaultModel(),
			'messages' => $requestMessages,
			'temperature' => $this->config->getTemperature(),
			'max_tokens' => $this->config->getMaxTokens(),
		];

		return $this->makeRequest('chat/completions', $payload, $apiKey);
	}

	/**
	 * Build messages array for API request
	 *
	 * @param string $prompt Current prompt
	 * @param array $history Previous messages
	 * @return array
	 */
	private function buildMessages(string $prompt, array $history): array {
		$messages = [];

		// Add system message if configured
		$systemPrompt = $this->config->getSystemPrompt();
		if (!empty($systemPrompt)) {
			$messages[] = [
				'role' => 'system',
				'content' => $systemPrompt,
			];
		}

		// Add conversation history
		foreach ($history as $message) {
			$messages[] = [
				'role' => $message['role'] ?? 'user',
				'content' => $message['content'] ?? '',
			];
		}

		// Add current prompt
		$messages[] = [
			'role' => 'user',
			'content' => $prompt,
		];

		return $messages;
	}

	/**
	 * Make API request with retry logic
	 *
	 * @param string $endpoint API endpoint
	 * @param array $payload Request payload
	 * @param string $apiKey API key
	 * @return string Response content
	 * @throws \Exception
	 */
	private function makeRequest(string $endpoint, array $payload, string $apiKey): string {
		$url = self::API_BASE_URL . '/' . $endpoint;
		$attempt = 0;

		while ($attempt < self::MAX_RETRIES) {
			$attempt++;

			try {
				$response = $this->httpClient->post(
					$url,
					[
						'headers' => [
							'Authorization' => 'Bearer ' . $apiKey,
							'Content-Type' => 'application/json',
							'OpenAI-Organization' => $this->config->getOrganizationId() ?: null,
						],
						'body' => json_encode($payload),
						'timeout' => self::TIMEOUT,
					]
				);

				$statusCode = $response->getStatusCode();
				$body = $response->getBody();

				if ($statusCode === 200) {
					$data = json_decode($body, true);
					return $this->parseChatResponse($data);
				}

				if ($statusCode === 429 || ($statusCode >= 500 && $statusCode < 600)) {
					// Rate limit or server error - retry
					$waitTime = pow(2, $attempt);
					$this->logger->warning("OpenAI API rate limited, waiting {$waitTime}s before retry");
					usleep($waitTime * 1000000);
					continue;
				}

				$error = $this->parseErrorResponse($body);
				throw new \Exception("OpenAI API error ({$statusCode}): {$error}");

			} catch (\Exception $e) {
				$this->logger->error("OpenAI request failed (attempt {$attempt}): " . $e->getMessage());

				if ($attempt >= self::MAX_RETRIES) {
					throw new \Exception('OpenAI request failed after ' . self::MAX_RETRIES . ' retries: ' . $e->getMessage());
				}

				$waitTime = pow(2, $attempt);
				usleep($waitTime * 1000000);
			}
		}

		throw new \Exception('OpenAI request failed after maximum retries');
	}

	/**
	 * Parse chat completion response
	 *
	 * @param array $data Response data
	 * @return string
	 * @throws \Exception
	 */
	private function parseChatResponse(array $data): string {
		if (!isset($data['choices'][0]['message']['content'])) {
			throw new \Exception('Invalid response format from OpenAI');
		}

		return $data['choices'][0]['message']['content'];
	}

	/**
	 * Parse error response
	 *
	 * @param string $body Response body
	 * @return string
	 */
	private function parseErrorResponse(string $body): string {
		$data = json_decode($body, true);
		
		if (isset($data['error']['message'])) {
			return $data['error']['message'];
		}

		return $body;
	}

	/**
	 * Check API connection and credentials
	 *
	 * @return bool
	 */
	public function testConnection(): bool {
		try {
			$apiKey = $this->config->getApiKey();
			
			if (empty($apiKey)) {
				return false;
			}

			$response = $this->httpClient->get(
				self::API_BASE_URL . '/models',
				[
					'headers' => [
						'Authorization' => 'Bearer ' . $apiKey,
						'OpenAI-Organization' => $this->config->getOrganizationId() ?: null,
					],
					'timeout' => 10,
				]
			);

			return $response->getStatusCode() === 200;
		} catch (\Exception $e) {
			$this->logger->error('OpenAI connection test failed: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Get available models
	 *
	 * @return array
	 */
	public function listModels(): array {
		try {
			$apiKey = $this->config->getApiKey();
			
			if (empty($apiKey)) {
				return [];
			}

			$response = $this->httpClient->get(
				self::API_BASE_URL . '/models',
				[
					'headers' => [
						'Authorization' => 'Bearer ' . $apiKey,
					],
					'timeout' => 10,
				]
			);

			if ($response->getStatusCode() === 200) {
				$data = json_decode($response->getBody(), true);
				return $data['data'] ?? [];
			}

			return [];
		} catch (\Exception $e) {
			$this->logger->error('Failed to list models: ' . $e->getMessage());
			return [];
		}
	}
}
