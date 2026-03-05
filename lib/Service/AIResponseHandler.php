<?php
/**
 * Nextcloud Talk Bot - Unified AI Response Handler
 * US-009: Einheitliche Response-Struktur, Context-Memory, Error Handling
 */
declare(strict_types=1);

namespace OCA\TalkBot\Service;

use Psr\Log\LoggerInterface;

class AIResponseHandler {

	/** @var array */
	private array $conversationHistory = [];

	/** @var int */
	private int $maxHistoryLength;

	/** @var string */
	private string $currentProvider;

	public function __construct(
		private AIProviderDetector $providerDetector,
		private NextcloudAIService $nextcloudAI,
		private OpenAIService $openAI,
		private CustomAIService $customAI,
		private LoggerInterface $logger
	) {
		$this->maxHistoryLength = 20; // Default max messages in context
	}

	/**
	 * Process an AI request with automatic provider selection
	 *
	 * @param string $prompt User message
	 * @param array $options Additional options
	 * @return AIResponse
	 */
	public function processRequest(string $prompt, array $options = []): AIResponse {
		$provider = $options['provider'] ?? $this->providerDetector->detectProvider();
		$this->currentProvider = $provider;

		// Add user message to history
		$this->addToHistory('user', $prompt);

		try {
			$response = $this->routeToProvider($prompt, $options);
			
			// Add assistant response to history
			$this->addToHistory('assistant', $response);

			return new AIResponse(
				success: true,
				provider: $provider,
				content: $response,
				history: $this->getHistory(),
				error: null
			);

		} catch (\Exception $e) {
			$this->logger->error("AI request failed: " . $e->getMessage());

			// Try fallback provider
			$fallbackResponse = $this->tryFallback($prompt, $provider, $options);

			if ($fallbackResponse !== null) {
				$this->addToHistory('assistant', $fallbackResponse);
				
				return new AIResponse(
					success: true,
					provider: $this->currentProvider,
					content: $fallbackResponse,
					history: $this->getHistory(),
					error: "Fallback to {$this->currentProvider} successful",
					fallback: true
				);
			}

			return new AIResponse(
				success: false,
				provider: $provider,
				content: '',
				history: $this->getHistory(),
				error: $e->getMessage()
			);
		}
	}

	/**
	 * Route request to appropriate provider
	 *
	 * @param string $prompt
	 * @param array $options
	 * @return string
	 * @throws \Exception
	 */
	private function routeToProvider(string $prompt, array $options = []): string {
		$messages = $this->getHistoryForProvider();

		switch ($this->currentProvider) {
			case AIProviderDetector::PROVIDER_NEXTCLOUD_AI:
				return $this->nextcloudAI->processChat($prompt);

			case AIProviderDetector::PROVIDER_OPENAI:
				return $this->openAI->chat($prompt, $messages);

			case AIProviderDetector::PROVIDER_CUSTOM:
				return $this->customAI->chat($prompt, $messages);

			default:
				throw new \Exception('No AI provider available');
		}
	}

	/**
	 * Try fallback provider
	 *
	 * @param string $prompt
	 * @param string $originalProvider
	 * @param array $options
	 * @return string|null
	 */
	private function tryFallback(string $prompt, string $originalProvider, array $options = []): ?string {
		$fallbackOrder = [
			AIProviderDetector::PROVIDER_OPENAI,
			AIProviderDetector::PROVIDER_NEXTCLOUD_AI,
			AIProviderDetector::PROVIDER_CUSTOM,
		];

		foreach ($fallbackOrder as $provider) {
			if ($provider === $originalProvider) {
				continue;
			}

			$this->currentProvider = $provider;

			try {
				if ($this->isProviderAvailable($provider)) {
					$this->logger->info("Trying fallback provider: {$provider}");
					return $this->routeToProvider($prompt, $options);
				}
			} catch (\Exception $e) {
				$this->logger->warning("Fallback provider {$provider} failed: " . $e->getMessage());
			}
		}

		return null;
	}

	/**
	 * Check if provider is available
	 *
	 * @param string $provider
	 * @return bool
	 */
	private function isProviderAvailable(string $provider): bool {
		switch ($provider) {
			case AIProviderDetector::PROVIDER_NEXTCLOUD_AI:
				return $this->nextcloudAI->isAvailable();
			case AIProviderDetector::PROVIDER_OPENAI:
				return $this->openAI->testConnection();
			case AIProviderDetector::PROVIDER_CUSTOM:
				return $this->customAI->testConnection();
			default:
				return false;
		}
	}

	/**
	 * Add message to conversation history
	 *
	 * @param string $role Role (user/assistant/system)
	 * @param string $content Message content
	 * @return void
	 */
	public function addToHistory(string $role, string $content): void {
		$this->conversationHistory[] = [
			'role' => $role,
			'content' => $content,
			'timestamp' => time(),
		];

		// Trim history if too long
		if (count($this->conversationHistory) > $this->maxHistoryLength) {
			$this->conversationHistory = array_slice(
				$this->conversationHistory,
				-$this->maxHistoryLength
			);
		}
	}

	/**
	 * Get conversation history
	 *
	 * @return array
	 */
	public function getHistory(): array {
		return $this->conversationHistory;
	}

	/**
	 * Get history formatted for provider
	 *
	 * @return array
	 */
	private function getHistoryForProvider(): array {
		$history = [];
		
		foreach ($this->conversationHistory as $message) {
			// Skip system messages for some providers
			if ($message['role'] === 'system' && 
				$this->currentProvider === AIProviderDetector::PROVIDER_NEXTCLOUD_AI) {
				continue;
			}
			
			$history[] = [
				'role' => $message['role'],
				'content' => $message['content'],
			];
		}

		return $history;
	}

	/**
	 * Clear conversation history
	 *
	 * @return void
	 */
	public function clearHistory(): void {
		$this->conversationHistory = [];
	}

	/**
	 * Set max history length
	 *
	 * @param int $length
	 * @return void
	 */
	public function setMaxHistoryLength(int $length): void {
		$this->maxHistoryLength = $length;
	}

	/**
	 * Get current provider
	 *
	 * @return string
	 */
	public function getCurrentProvider(): string {
		return $this->currentProvider;
	}

	/**
	 * Get available providers
	 *
	 * @return array
	 */
	public function getAvailableProviders(): array {
		return $this->providerDetector->getAvailableProviders();
	}

	/**
	 * Detect best available provider
	 *
	 * @return string
	 */
	public function detectBestProvider(): string {
		return $this->providerDetector->detectProvider();
	}

	/**
	 * Set system prompt for context
	 *
	 * @param string $systemPrompt
	 * @return void
	 */
	public function setSystemPrompt(string $systemPrompt): void {
		// Remove existing system prompt
		$this->conversationHistory = array_filter(
			$this->conversationHistory,
			fn($msg) => $msg['role'] !== 'system'
		);

		// Add new system prompt at the beginning
		array_unshift($this->conversationHistory, [
			'role' => 'system',
			'content' => $systemPrompt,
			'timestamp' => time(),
		]);
	}
}

/**
 * AI Response DTO
 */
class AIResponse {
	public function __construct(
		public readonly bool $success,
		public readonly string $provider,
		public readonly string $content,
		public readonly array $history,
		public readonly ?string $error = null,
		public readonly bool $fallback = false,
	) {}

	/**
	 * Convert to array
	 *
	 * @return array
	 */
	public function toArray(): array {
		return [
			'success' => $this->success,
			'provider' => $this->provider,
			'content' => $this->content,
			'history' => $this->history,
			'error' => $this->error,
			'fallback' => $this->fallback,
		];
	}

	/**
	 * Get content or default
	 *
	 * @param string $default
	 * @return string
	 */
	public function getContentOrDefault(string $default = ''): string {
		return $this->success ? $this->content : $default;
	}
}
