<?php
/**
 * Nextcloud Talk Bot - Nextcloud AI Service
 * US-006: Integration mit Nextcloud TextProcessing
 */
declare(strict_types=1);

namespace OCA\TalkBot\Service;

use OCP\TextProcessing\IManager;
use OCP\TextProcessing\ChatContext;
use OCP\TextProcessing\Task;
use OCP\AppFramework\Db\DoesNotExistException;
use Psr\Log\LoggerInterface;

class NextcloudAIService {

	public function __construct(
		private IManager $textProcessingManager,
		private LoggerInterface $logger
	) {}

	/**
	 * Check if Nextcloud AI is available
	 *
	 * @return bool
	 */
	public function isAvailable(): bool {
		try {
			return $this->textProcessingManager->hasProviders();
		} catch (\Exception $e) {
			$this->logger->warning('Nextcloud AI availability check failed: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Get available AI task types
	 *
	 * @return array
	 */
	public function getAvailableTaskTypes(): array {
		try {
			$providers = $this->textProcessingManager->getProviders();
			$types = [];
			
			foreach ($providers as $provider) {
				$types[] = [
					'name' => $provider->getName(),
					'taskType' => $provider->getTaskType(),
				];
			}
			
			return $types;
		} catch (\Exception $e) {
			$this->logger->warning('Failed to get AI task types: ' . $e->getMessage());
			return [];
		}
	}

	/**
	 * Process a chat message using Nextcloud AI
	 *
	 * @param string $prompt The user prompt
	 * @param ChatContext|null $context Conversation context
	 * @return string AI response
	 * @throws \Exception
	 */
	public function processChat(string $prompt, ?ChatContext $context = null): string {
		if (!$this->isAvailable()) {
			throw new \Exception('Nextcloud AI is not available');
		}

		try {
			// Create a chat task
			$task = new Task(
				Task::TYPE_CHAT,
				$prompt,
				null, // no input file
				$context
			);

			// Process the task
			$this->textProcessingManager->processTask($task);

			// Get the output
			$output = $task->getOutput();
			
			if ($output === null) {
				throw new \Exception('AI task returned no output');
			}

			return $output;
		} catch (DoesNotExistException $e) {
			$this->logger->error('AI provider does not exist: ' . $e->getMessage());
			throw new \Exception('No suitable AI provider found');
		} catch (\Exception $e) {
			$this->logger->error('Nextcloud AI processing failed: ' . $e->getMessage());
			throw new \Exception('AI processing failed: ' . $e->getMessage());
		}
	}

	/**
	 * Process a chat message asynchronously
	 *
	 * @param string $prompt The user prompt
	 * @param ChatContext|null $context Conversation context
	 * @return Task The created task
	 * @throws \Exception
	 */
	public function processChatAsync(string $prompt, ?ChatContext $context = null): Task {
		if (!$this->isAvailable()) {
			throw new \Exception('Nextcloud AI is not available');
		}

		try {
			$task = new Task(
				Task::TYPE_CHAT,
				$prompt,
				null,
				$context
			);

			$this->textProcessingManager->runTask($task);
			
			return $task;
		} catch (\Exception $e) {
			$this->logger->error('Failed to start async AI task: ' . $e->getMessage());
			throw new \Exception('Failed to start async AI task: ' . $e->getMessage());
		}
	}

	/**
	 * Create a chat context for conversation history
	 *
	 * @param array $history Message history
	 * @return ChatContext
	 */
	public function createChatContext(array $history): ChatContext {
		return new ChatContext($history);
	}

	/**
	 * Get the status of an async task
	 *
	 * @param Task $task
	 * @return string Status
	 */
	public function getTaskStatus(Task $task): string {
		return $task->getStatus();
	}

	/**
	 * Get the output of a completed task
	 *
	 * @param Task $task
	 * @return string|null
	 */
	public function getTaskOutput(Task $task): ?string {
		if ($task->getStatus() === Task::STATUS_SUCCESS) {
			return $task->getOutput();
		}
		return null;
	}
}
